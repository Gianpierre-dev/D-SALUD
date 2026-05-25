<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LotesStockBajoExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $productos)
    {
    }

    public function collection(): Collection
    {
        return $this->productos;
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
     * @param  mixed  $producto
     * @return array<int, mixed>
     */
    public function map($producto): array
    {
        return [
            $producto->nombre,
            (int) $producto->lotes_sum_stock,
            $producto->stock_minimo,
        ];
    }
}
