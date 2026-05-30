<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\DetalleVenta;
use App\Models\Venta;
use App\Support\CsvSafe;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export de productos más vendidos con FromQuery + WithChunkReading: el agrupado
 * se hace en la BD y se itera en lotes de 1000 para mantener memoria constante.
 */
class ProductosMasVendidosExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Carbon $inicio,
        private readonly Carbon $fin,
    ) {
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

    public function chunkSize(): int
    {
        return 1000;
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
