<?php

namespace App\Services;

use App\Models\RegistroAuditoria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Consulta el log de auditoría con filtros opcionales.
 * NO registra eventos (eso es responsabilidad de AuditoriaService).
 */
class AuditoriaConsultaService
{
    /**
     * Lista paginada de registros de auditoría con filtros opcionales.
     *
     * @param  string|null  $buscar  Filtra por módulo o acción (like).
     * @param  string|null  $modulo  Filtra por módulo exacto.
     * @param  string|null  $fecha   Filtra por fecha exacta (Y-m-d).
     */
    public function paginar(
        ?string $buscar,
        ?string $modulo,
        ?string $fecha,
    ): LengthAwarePaginator {
        return RegistroAuditoria::query()
            ->with('user')
            ->when(
                $buscar,
                fn ($q, $termino) => $q->where(function ($sub) use ($termino) {
                    $sub->where('modulo', 'like', "%{$termino}%")
                        ->orWhere('accion', 'like', "%{$termino}%");
                }),
            )
            ->when($modulo, fn ($q, $m) => $q->where('modulo', $m))
            ->when($fecha, fn ($q, $f) => $q->whereDate('created_at', $f))
            ->orderByDesc('created_at')
            ->paginate(config('dsalud.paginacion.por_pagina'))
            ->withQueryString();
    }

    /**
     * Devuelve la lista de valores distintos de módulo para el filtro select.
     *
     * @return Collection<int, string>
     */
    public function modulos(): Collection
    {
        return RegistroAuditoria::query()
            ->select('modulo')
            ->distinct()
            ->orderBy('modulo')
            ->pluck('modulo');
    }
}
