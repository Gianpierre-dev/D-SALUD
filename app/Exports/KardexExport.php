<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Support\CsvSafe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class KardexExport extends ReporteEjecutivoExport
{
    public function __construct(
        private readonly int $productoId,
        private readonly Carbon $inicio,
        private readonly Carbon $fin,
    ) {
    }

    protected function tituloReporte(): string
    {
        return 'Kardex por producto';
    }

    protected function subtituloReporte(): ?string
    {
        $producto = Producto::query()->find($this->productoId);
        $nombre   = $producto?->nombre ?? "Producto #{$this->productoId}";

        return sprintf(
            '%s · Periodo: %s — %s',
            $nombre,
            $this->inicio->format('d/m/Y'),
            $this->fin->format('d/m/Y'),
        );
    }

    public function query(): Builder
    {
        return MovimientoInventario::query()
            ->with(['lote:id,codigo_lote', 'usuario:id,name'])
            ->where('producto_id', $this->productoId)
            ->whereBetween('created_at', [$this->inicio->startOfDay(), $this->fin->endOfDay()])
            ->orderBy('created_at');
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
