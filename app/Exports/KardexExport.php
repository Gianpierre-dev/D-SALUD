<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\MovimientoInventario;
use App\Support\CsvSafe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export Kardex por producto: lista cronológica de todos los movimientos
 * (ENTRADAS y SALIDAS) con stock_anterior y stock_posterior visibles.
 *
 * FromQuery + WithChunkReading mantienen memoria constante incluso con
 * cientos de miles de filas — mismo patrón que VentasPorPeriodoExport.
 */
class KardexExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping
{
    public function __construct(
        private readonly int $productoId,
        private readonly Carbon $inicio,
        private readonly Carbon $fin,
    ) {
    }

    public function query(): Builder
    {
        return MovimientoInventario::query()
            ->with(['lote:id,codigo_lote', 'usuario:id,name'])
            ->where('producto_id', $this->productoId)
            ->whereBetween('created_at', [$this->inicio->startOfDay(), $this->fin->endOfDay()])
            ->orderBy('created_at');
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
            'Fecha',
            'Tipo',
            'Motivo',
            'Lote',
            'Cantidad',
            'Stock anterior',
            'Stock posterior',
            'Usuario',
            'Observación',
            'Referencia',
        ];
    }

    /**
     * @param  MovimientoInventario  $m
     * @return array<int, mixed>
     */
    public function map($m): array
    {
        return [
            $m->created_at->format('d/m/Y H:i:s'),
            $m->tipo->value,
            CsvSafe::escape($m->motivo->etiqueta()),
            CsvSafe::escape($m->lote?->codigo_lote ?? '—'),
            (int) $m->cantidad,
            (int) $m->stock_anterior,
            (int) $m->stock_posterior,
            CsvSafe::escape($m->usuario?->name ?? '—'),
            CsvSafe::escape($m->observacion ?? '—'),
            CsvSafe::escape($m->referencia_tipo ? "{$m->referencia_tipo}#{$m->referencia_id}" : '—'),
        ];
    }
}
