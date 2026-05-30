<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Lote;
use App\Support\CsvSafe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export de lotes próximos a vencer con FromQuery + WithChunkReading: itera la
 * tabla `lotes` en bloques de 1000 y mantiene memoria constante incluso con
 * inventarios de cientos de miles de lotes.
 */
class ProductosPorVencerExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping
{
    public function __construct(private readonly Carbon $limite)
    {
    }

    public function query(): Builder
    {
        return Lote::query()
            ->with('producto:id,nombre')
            ->where('stock', '>', 0)
            ->where('fecha_vencimiento', '<=', $this->limite)
            ->orderBy('fecha_vencimiento');
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
            'Código Lote',
            'Fecha Vencimiento',
            'Stock',
            'Días Restantes',
        ];
    }

    /**
     * @param  Lote  $lote
     * @return array<int, mixed>
     */
    public function map($lote): array
    {
        $diasRestantes = (int) now()->startOfDay()->diffInDays($lote->fecha_vencimiento, false);

        return [
            CsvSafe::escape($lote->producto?->nombre ?? '—'),
            CsvSafe::escape((string) $lote->codigo_lote),
            $lote->fecha_vencimiento->format('d/m/Y'),
            (int) $lote->stock,
            $diasRestantes,
        ];
    }
}
