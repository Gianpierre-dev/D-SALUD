<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\RegistroAuditoria;
use App\Support\CsvSafe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AuditoriaExport extends ReporteEjecutivoExport
{
    public function __construct(
        private readonly ?Carbon $inicio = null,
        private readonly ?Carbon $fin = null,
    ) {
    }

    protected function tituloReporte(): string
    {
        return 'Registro de auditoría';
    }

    protected function subtituloReporte(): ?string
    {
        if ($this->inicio === null && $this->fin === null) {
            return 'Histórico completo del sistema';
        }

        $desde = $this->inicio?->format('d/m/Y') ?? 'inicio';
        $hasta = $this->fin?->format('d/m/Y')    ?? 'hoy';

        return sprintf('Periodo: %s — %s', $desde, $hasta);
    }

    public function query(): Builder
    {
        return RegistroAuditoria::query()
            ->with('user:id,name')
            ->when($this->inicio, fn ($q, Carbon $i) => $q->where('created_at', '>=', $i->startOfDay()))
            ->when($this->fin, fn ($q, Carbon $f) => $q->where('created_at', '<=', $f->endOfDay()))
            ->orderByDesc('created_at');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Fecha', 'Usuario', 'Módulo', 'Acción', 'IP', 'Detalle'];
    }

    /**
     * @param  RegistroAuditoria  $registro
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
