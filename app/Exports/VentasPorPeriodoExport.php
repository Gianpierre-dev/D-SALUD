<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Venta;
use App\Support\CsvSafe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export de ventas con FromQuery + WithChunkReading: escalable a períodos
 * largos sin cargar todas las ventas en memoria.
 */
class VentasPorPeriodoExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Carbon $inicio,
        private readonly Carbon $fin,
    ) {
    }

    public function query(): Builder
    {
        return Venta::query()
            ->with(['boleta:id,venta_id,serie,numero', 'vendedor:id,name'])
            ->where('estado', Venta::ESTADO_COMPLETADA)
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
        return ['N° Boleta', 'Fecha', 'Vendedor', 'Total (S/)'];
    }

    /**
     * @param  Venta  $venta
     * @return array<int, mixed>
     */
    public function map($venta): array
    {
        return [
            CsvSafe::escape($venta->boleta?->numero_formateado ?? '—'),
            $venta->created_at->format('d/m/Y H:i'),
            CsvSafe::escape($venta->vendedor?->name ?? '—'),
            number_format((float) $venta->total, 2),
        ];
    }
}
