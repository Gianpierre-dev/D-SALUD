<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MedioPago;
use App\Models\DetalleVenta;
use App\Models\Lote;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio para los indicadores y gráficos del dashboard.
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

    /**
     * Serie de ventas COMPLETADAS de los últimos N días (incluido hoy),
     * con los días sin ventas en 0 para que el gráfico no tenga huecos.
     *
     * @return array<int, array{fecha: string, etiqueta: string, total: float}>
     */
    public function ventasUltimosDias(int $dias = 14): array
    {
        $desde = Carbon::today()->subDays($dias - 1);

        // whereBetween es sargable (usa el índice de created_at); el groupBy
        // por DATE() opera sobre el subconjunto ya acotado a N días.
        $porDia = Venta::query()
            ->where('estado', Venta::ESTADO_COMPLETADA)
            ->whereBetween('created_at', [$desde->copy()->startOfDay(), Carbon::today()->endOfDay()])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) as dia, SUM(total) as total')
            ->pluck('total', 'dia');

        // Rellenar los días faltantes con 0.
        $serie = [];
        for ($i = 0; $i < $dias; $i++) {
            $fecha = $desde->copy()->addDays($i);
            $clave = $fecha->format('Y-m-d');
            $serie[] = [
                'fecha'    => $clave,
                'etiqueta' => $fecha->format('d/m'),
                'total'    => (float) ($porDia[$clave] ?? 0),
            ];
        }

        return $serie;
    }

    /**
     * Top de productos por cantidad vendida en el mes actual.
     *
     * @return array<int, array{nombre: string, cantidad: int}>
     */
    public function topProductosDelMes(int $limite = 5): array
    {
        return DetalleVenta::query()
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->where('ventas.estado', Venta::ESTADO_COMPLETADA)
            ->whereBetween('ventas.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->groupBy('detalle_ventas.producto_id', 'productos.nombre')
            ->orderByDesc('cantidad')
            ->limit($limite)
            ->selectRaw('productos.nombre as nombre, SUM(detalle_ventas.cantidad) as cantidad')
            ->get()
            ->map(fn ($fila) => [
                'nombre'   => (string) $fila->nombre,
                'cantidad' => (int) $fila->cantidad,
            ])
            ->all();
    }

    /**
     * Distribución de ventas del mes por medio de pago (para el gráfico de dona).
     * Solo incluye medios con monto > 0.
     *
     * @return array<int, array{medio: string, total: float}>
     */
    public function ventasPorMedioDelMes(): array
    {
        $sumas = Pago::query()
            ->join('ventas', 'pagos.venta_id', '=', 'ventas.id')
            ->where('ventas.estado', Venta::ESTADO_COMPLETADA)
            ->whereBetween('ventas.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->groupBy('pagos.medio_pago')
            ->selectRaw('pagos.medio_pago as medio, SUM(pagos.monto) as total')
            ->get();

        return $sumas
            ->map(fn ($fila) => [
                'medio' => MedioPago::from($fila->medio)->etiqueta(),
                'total' => (float) $fila->total,
            ])
            ->filter(fn ($f) => $f['total'] > 0)
            ->values()
            ->all();
    }
}
