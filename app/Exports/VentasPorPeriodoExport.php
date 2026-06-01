<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Venta;
use App\Support\CsvSafe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class VentasPorPeriodoExport extends ReporteEjecutivoExport
{
    public function __construct(
        private readonly Carbon $inicio,
        private readonly Carbon $fin,
    ) {
    }

    protected function tituloReporte(): string
    {
        return 'Reporte de ventas por período';
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
        return Venta::query()
            ->with(['boleta:id,venta_id,serie,numero', 'vendedor:id,name'])
            ->where('estado', Venta::ESTADO_COMPLETADA)
            ->whereBetween('created_at', [$this->inicio->startOfDay(), $this->fin->endOfDay()])
            ->orderBy('created_at');
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
