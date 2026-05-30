<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\RegistroAuditoria;
use App\Support\CsvSafe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export del log de auditoría usando FromQuery + WithChunkReading: procesa
 * registros en lotes de 1000, manteniendo memoria constante incluso con
 * cientos de miles de filas (evita OOM bajo memory_limit reducido en Railway).
 */
class AuditoriaExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping
{
    public function __construct(
        private readonly ?Carbon $inicio = null,
        private readonly ?Carbon $fin = null,
    ) {
    }

    public function query(): Builder
    {
        return RegistroAuditoria::query()
            ->with('user:id,name')
            ->when($this->inicio, fn ($q, Carbon $i) => $q->where('created_at', '>=', $i->startOfDay()))
            ->when($this->fin, fn ($q, Carbon $f) => $q->where('created_at', '<=', $f->endOfDay()))
            ->orderByDesc('created_at');
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
