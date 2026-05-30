<?php

declare(strict_types=1);

namespace App\Exports;

use App\Support\CsvSafe;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AuditoriaExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $registros)
    {
    }

    public function collection(): Collection
    {
        return $this->registros;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Fecha',
            'Usuario',
            'Módulo',
            'Acción',
            'IP',
            'Detalle',
        ];
    }

    /**
     * @param  mixed  $registro
     * @return array<int, mixed>
     */
    public function map($registro): array
    {
        return [
            $registro->created_at->format('d/m/Y H:i:s'),
            CsvSafe::escape($registro->user?->name ?? '—'),
            CsvSafe::escape($registro->modulo),
            CsvSafe::escape($registro->accion),
            CsvSafe::escape($registro->ip ?? '—'),
            CsvSafe::escape($registro->detalle ?? '—'),
        ];
    }
}
