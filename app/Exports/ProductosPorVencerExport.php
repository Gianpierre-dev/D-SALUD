<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductosPorVencerExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $lotes)
    {
    }

    public function collection(): Collection
    {
        return $this->lotes;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Producto',
            'Código Lote',
            'Fecha Vencimiento',
            'Stock',
            'Días Restantes',
        ];
    }

    /**
     * @param  mixed  $lote
     * @return array<int, mixed>
     */
    public function map($lote): array
    {
        $diasRestantes = (int) now()->startOfDay()->diffInDays($lote->fecha_vencimiento, false);

        return [
            $lote->producto?->nombre ?? '—',
            $lote->codigo_lote,
            $lote->fecha_vencimiento->format('d/m/Y'),
            $lote->stock,
            $diasRestantes,
        ];
    }
}
