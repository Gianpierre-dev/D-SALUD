<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Producto;
use App\Support\CsvSafe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export de productos con stock total por debajo del mínimo configurado.
 * El cálculo de stock se hace en SQL (leftJoinSub) y se pagina en bloques de 1000.
 */
class LotesStockBajoExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        $aggregate = DB::table('lotes')
            ->select('producto_id', DB::raw('COALESCE(SUM(stock), 0) as lotes_sum_stock'))
            ->groupBy('producto_id');

        return Producto::query()
            ->where('productos.activo', true)
            ->leftJoinSub($aggregate, 'agg', 'agg.producto_id', '=', 'productos.id')
            ->select('productos.id', 'productos.nombre', 'productos.stock_minimo')
            ->selectRaw('COALESCE(agg.lotes_sum_stock, 0) as lotes_sum_stock')
            ->whereRaw('COALESCE(agg.lotes_sum_stock, 0) <= productos.stock_minimo')
            ->orderBy('productos.nombre');
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
            'Stock Actual',
            'Stock Mínimo',
        ];
    }

    /**
     * @param  Producto  $producto
     * @return array<int, mixed>
     */
    public function map($producto): array
    {
        return [
            CsvSafe::escape((string) $producto->nombre),
            (int) $producto->lotes_sum_stock,
            (int) $producto->stock_minimo,
        ];
    }
}
