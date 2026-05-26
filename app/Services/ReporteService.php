<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DetalleVenta;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lógica de datos para el módulo de reportes.
 * Los controladores delegan aquí; los Exports reciben colecciones ya preparadas.
 */
class ReporteService
{
    /**
     * Ventas COMPLETADAS en el rango de fechas, con boleta y vendedor.
     */
    public function ventasPorPeriodo(Carbon $fechaInicio, Carbon $fechaFin): Collection
    {
        return Venta::query()
            ->with(['boleta', 'vendedor'])
            ->where('estado', Venta::ESTADO_COMPLETADA)
            ->whereBetween('created_at', [
                $fechaInicio->startOfDay(),
                $fechaFin->copy()->endOfDay(),
            ])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Productos más vendidos en el rango, agrupados por producto,
     * sumando cantidad y subtotal (solo ventas COMPLETADAS).
     */
    public function productosMasVendidos(Carbon $fechaInicio, Carbon $fechaFin): Collection
    {
        return DetalleVenta::query()
            ->selectRaw('
                detalle_ventas.producto_id,
                productos.nombre AS nombre_producto,
                SUM(detalle_ventas.cantidad) AS cantidad_total,
                SUM(detalle_ventas.subtotal)  AS total_vendido
            ')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->where('ventas.estado', Venta::ESTADO_COMPLETADA)
            ->whereBetween('ventas.created_at', [
                $fechaInicio->startOfDay(),
                $fechaFin->copy()->endOfDay(),
            ])
            ->groupBy('detalle_ventas.producto_id', 'productos.nombre')
            ->orderByDesc('cantidad_total')
            ->get();
    }

    /**
     * Lotes con stock > 0 cuya fecha de vencimiento cae dentro del umbral
     * configurado en dsalud.inventario.dias_alerta_vencimiento.
     */
    public function productosPorVencer(): Collection
    {
        $diasAlerta = (int) config('dsalud.inventario.dias_alerta_vencimiento');
        $limite = now()->addDays($diasAlerta)->endOfDay();

        return Lote::query()
            ->with('producto')
            ->where('stock', '>', 0)
            ->where('fecha_vencimiento', '<=', $limite)
            ->orderBy('fecha_vencimiento')
            ->get();
    }

    /**
     * Productos activos cuyo stock total (suma de lotes) es menor o igual
     * al stock mínimo configurado en el producto.
     */
    public function lotesStockBajo(): Collection
    {
        return Producto::query()
            ->withSum('lotes', 'stock')
            ->where('activo', true)
            ->get()
            ->filter(fn (Producto $producto): bool =>
                (int) $producto->lotes_sum_stock <= $producto->stock_minimo
            )
            ->values();
    }

    /**
     * Registros de auditoría con el usuario que los generó,
     * opcionalmente filtrados por rango de fechas.
     */
    public function auditoria(?Carbon $fechaInicio, ?Carbon $fechaFin): Collection
    {
        return RegistroAuditoria::query()
            ->with('user')
            ->when(
                $fechaInicio,
                fn ($q) => $q->where('created_at', '>=', $fechaInicio->startOfDay())
            )
            ->when(
                $fechaFin,
                fn ($q) => $q->where('created_at', '<=', $fechaFin->copy()->endOfDay())
            )
            ->orderByDesc('created_at')
            ->get();
    }
}
