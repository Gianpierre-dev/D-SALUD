<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Venta;
use App\Support\CsvSafe;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductosMasVendidosExport extends ReporteEjecutivoExport
{
    public function __construct(
        private readonly Carbon $inicio,
        private readonly Carbon $fin,
    ) {
    }

    protected function tituloReporte(): string
    {
        return 'Productos más vendidos';
    }

    protected function subtituloReporte(): ?string
    {
        return sprintf(
            'Periodo: %s — %s',
            $this->inicio->format('d/m/Y'),
            $this->fin->format('d/m/Y'),
        );
    }

    public function query(): Builder
    {
        return DB::table('detalle_ventas')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->where('ventas.estado', Venta::ESTADO_COMPLETADA)
            ->whereBetween('ventas.created_at', [
                $this->inicio->startOfDay(),
                $this->fin->endOfDay(),
            ])
            ->groupBy('detalle_ventas.producto_id', 'productos.nombre')
            ->orderByDesc('cantidad_total')
            ->select(
                'detalle_ventas.producto_id',
                'productos.nombre as nombre_producto',
                DB::raw('SUM(detalle_ventas.cantidad) as cantidad_total'),
                DB::raw('SUM(detalle_ventas.subtotal) as total_vendido'),
            );
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Producto',
            'Cantidad Vendida',
            'Total Vendido (S/)',
        ];
    }

    /**
     * @param  object  $fila
     * @return array<int, mixed>
     */
    public function map($fila): array
    {
        return [
            CsvSafe::escape((string) $fila->nombre_producto),
            (int) $fila->cantidad_total,
            number_format((float) $fila->total_vendido, 2),
        ];
    }
}
