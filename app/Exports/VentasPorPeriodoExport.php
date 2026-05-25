<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VentasPorPeriodoExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $ventas)
    {
    }

    public function collection(): Collection
    {
        return $this->ventas;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'N° Boleta',
            'Fecha',
            'Vendedor',
            'Total (S/)',
        ];
    }

    /**
     * @param  mixed  $venta
     * @return array<int, mixed>
     */
    public function map($venta): array
    {
        return [
            $venta->boleta?->numero_formateado ?? '—',
            $venta->created_at->format('d/m/Y H:i'),
            $venta->vendedor?->name ?? '—',
            number_format((float) $venta->total, 2),
        ];
    }
}
