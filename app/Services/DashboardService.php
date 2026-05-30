<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        // whereBetween sargable: usa el índice (ventas_estado_created_idx).
        $ventas = Venta::query()
            ->whereBetween('created_at', [Carbon::today(), Carbon::today()->endOfDay()])
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
        // Filtrado en SQL: en lugar de traer TODOS los productos a memoria y filtrar
        // con PHP (no escala con miles de filas), se hace un JOIN con la suma de stock
        // por producto y se compara contra stock_minimo directamente en la base.
        $aggregate = DB::table('lotes')
            ->select('producto_id', DB::raw('COALESCE(SUM(stock), 0) as stock_total'))
            ->groupBy('producto_id');

        return Producto::query()
            ->where('productos.activo', true)
            ->leftJoinSub($aggregate, 'agg', 'agg.producto_id', '=', 'productos.id')
            ->select('productos.*')
            ->selectRaw('COALESCE(agg.stock_total, 0) as stock_total')
            ->whereRaw('COALESCE(agg.stock_total, 0) <= productos.stock_minimo')
            ->orderBy('productos.nombre')
            ->limit(10)
            ->get();
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
