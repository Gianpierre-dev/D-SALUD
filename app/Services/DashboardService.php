<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lógica de negocio para los indicadores del dashboard del día.
 */
class DashboardService
{
    /**
     * Indicadores de ventas completadas del día actual.
     *
     * @return array{ventas: int, recaudado: float, productos_vendidos: int}
     */
    public function indicadoresDelDia(): array
    {
        $ventas = Venta::query()
            ->whereDate('created_at', Carbon::today())
            ->where('estado', Venta::ESTADO_COMPLETADA)
            ->withSum('detalles as total_productos_vendidos', 'cantidad')
            ->get();

        return [
            'ventas'             => $ventas->count(),
            'recaudado'          => (float) $ventas->sum('total'),
            'productos_vendidos' => (int) $ventas->sum('total_productos_vendidos'),
        ];
    }

    /**
     * Productos activos cuyo stock total es igual o inferior al stock mínimo.
     * Incluye productos sin stock (null o 0).
     *
     * @return Collection<int, Producto>
     */
    public function productosStockBajo(): Collection
    {
        return Producto::query()
            ->where('activo', true)
            ->withSum('lotes as stock_total', 'stock')
            ->get()
            ->filter(
                fn (Producto $producto) =>
                    ($producto->stock_total === null || (int) $producto->stock_total <= $producto->stock_minimo)
            )
            ->take(10)
            ->values();
    }

    /**
     * Lotes con stock disponible próximos a vencer dentro del umbral configurado.
     *
     * @return Collection<int, Lote>
     */
    public function productosPorVencer(): Collection
    {
        $diasAlerta = (int) config('dsalud.inventario.dias_alerta_vencimiento', 30);
        $hoy        = Carbon::today();
        $limite     = Carbon::today()->addDays($diasAlerta);

        return Lote::query()
            ->where('stock', '>', 0)
            ->whereBetween('fecha_vencimiento', [$hoy, $limite])
            ->with('producto:id,nombre,codigo')
            ->orderBy('fecha_vencimiento')
            ->limit(10)
            ->get();
    }
}
