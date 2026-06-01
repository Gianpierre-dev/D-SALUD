<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Lote;
use App\Support\CsvSafe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ProductosPorVencerExport extends ReporteEjecutivoExport
{
    public function __construct(private readonly Carbon $limite)
    {
    }

    protected function tituloReporte(): string
    {
        return 'Productos próximos a vencer';
    }

    protected function subtituloReporte(): ?string
    {
        return 'Lotes con stock disponible y vencimiento hasta el ' . $this->limite->format('d/m/Y');
    }

    public function query(): Builder
    {
        return Lote::query()
            ->with('producto:id,nombre')
            ->where('stock', '>', 0)
            ->where('fecha_vencimiento', '<=', $this->limite)
            ->orderBy('fecha_vencimiento');
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
