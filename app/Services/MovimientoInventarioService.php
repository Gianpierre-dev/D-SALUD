<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MotivoMovimiento;
use App\Enums\TipoMovimiento;
use App\Models\Lote;
use App\Models\MovimientoInventario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Núcleo del kardex.
 *
 * Toda mutación de stock pasa por acá: ventas, anulaciones, ajustes manuales,
 * mermas, vencimientos, devoluciones. La capa garantiza:
 *   - lockForUpdate sobre el lote (evita race conditions).
 *   - Snapshot de stock_anterior / stock_posterior antes/después.
 *   - Persistencia atómica: si falla algo, ni el lote ni el movimiento quedan.
 *   - Inmutabilidad del movimiento (no se ofrecen update/delete públicos).
 */
class MovimientoInventarioService
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    /**
     * Registra una ENTRADA sobre un lote (incrementa stock).
     *
     * @param  array{tipo: string, id: int}|null  $referencia  Origen del movimiento.
     */
    public function registrarEntrada(
        Lote $lote,
        MotivoMovimiento $motivo,
        int $cantidad,
        ?string $observacion = null,
        ?array $referencia = null,
        ?int $userId = null,
    ): MovimientoInventario {
        $this->validarMotivo($motivo, TipoMovimiento::ENTRADA);

        return $this->ejecutar(
            $lote,
            TipoMovimiento::ENTRADA,
            $motivo,
            $cantidad,
            $observacion,
            $referencia,
            $userId,
        );
    }

    /**
     * Registra una SALIDA sobre un lote (decrementa stock).
     * Lanza RuntimeException si el stock resultante quedaría negativo.
     *
     * @param  array{tipo: string, id: int}|null  $referencia
     */
    public function registrarSalida(
        Lote $lote,
        MotivoMovimiento $motivo,
        int $cantidad,
        ?string $observacion = null,
        ?array $referencia = null,
        ?int $userId = null,
    ): MovimientoInventario {
        $this->validarMotivo($motivo, TipoMovimiento::SALIDA);

        return $this->ejecutar(
            $lote,
            TipoMovimiento::SALIDA,
            $motivo,
            $cantidad,
            $observacion,
            $referencia,
            $userId,
        );
    }

    /**
     * Núcleo común: bloquea el lote, calcula snapshots, valida y persiste
     * movimiento + lote dentro de una transacción.
     *
     * Si ya estamos dentro de una transacción externa (ej. VentaService),
     * DB::transaction() se anida sin abrir una nueva (savepoint en MySQL).
     *
     * @throws \InvalidArgumentException  cantidad <= 0
     * @throws \RuntimeException          salida con stock insuficiente
     *
     * @param  array{tipo: string, id: int}|null  $referencia
     */
    private function ejecutar(
        Lote $lote,
        TipoMovimiento $tipo,
        MotivoMovimiento $motivo,
        int $cantidad,
        ?string $observacion,
        ?array $referencia,
        ?int $userId,
    ): MovimientoInventario {
        if ($cantidad <= 0) {
            throw new \InvalidArgumentException('La cantidad del movimiento debe ser mayor a 0.');
        }

        return DB::transaction(function () use (
            $lote, $tipo, $motivo, $cantidad, $observacion, $referencia, $userId
        ): MovimientoInventario {
            // Re-lock para asegurar consistencia incluso si el lote pasó por aquí
            // sin lock (algunos callers ya lo bloquearon vía FEFO; este lock es idempotente).
            $loteBloqueado = Lote::where('id', $lote->id)->lockForUpdate()->firstOrFail();

            $stockAnterior = $loteBloqueado->stock;
            $delta         = $tipo === TipoMovimiento::ENTRADA ? $cantidad : -$cantidad;
            $stockPosterior = $stockAnterior + $delta;

            if ($stockPosterior < 0) {
                throw new \RuntimeException(
                    "Stock insuficiente en el lote {$loteBloqueado->codigo_lote} ({$stockAnterior} disponibles, se requieren {$cantidad})."
                );
            }

            $loteBloqueado->stock = $stockPosterior;
            $loteBloqueado->save();

            $movimiento = MovimientoInventario::create([
                'lote_id'         => $loteBloqueado->id,
                'producto_id'     => $loteBloqueado->producto_id,
                'tipo'            => $tipo,
                'motivo'          => $motivo,
                'cantidad'        => $cantidad,
                'stock_anterior'  => $stockAnterior,
                'stock_posterior' => $stockPosterior,
                'referencia_tipo' => $referencia['tipo'] ?? null,
                'referencia_id'   => $referencia['id'] ?? null,
                'observacion'     => $observacion,
                'user_id'         => $userId ?? Auth::id(),
            ]);

            // Auditoría solo para movimientos MANUALES (los automáticos ya están
            // auditados por el caller — VentaService::registrar/anular).
            if (in_array($motivo, MotivoMovimiento::manuales(), true)) {
                $this->auditoria->registrar(
                    'inventario',
                    'movimiento',
                    sprintf(
                        '%s %s — lote %s — %d unidades — %s',
                        $tipo->value,
                        $motivo->etiqueta(),
                        $loteBloqueado->codigo_lote,
                        $cantidad,
                        $observacion ?? 'sin observación',
                    ),
                );
            }

            return $movimiento;
        });
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function validarMotivo(MotivoMovimiento $motivo, TipoMovimiento $tipoEsperado): void
    {
        if ($motivo->tipo() !== $tipoEsperado) {
            throw new \InvalidArgumentException(
                "El motivo {$motivo->value} no corresponde a un movimiento {$tipoEsperado->value}."
            );
        }
    }

    /**
     * Listado paginado del kardex con filtros opcionales.
     *
     * @param  array{producto_id?: int|null, lote_id?: int|null, tipo?: string|null, motivo?: string|null, desde?: string|null, hasta?: string|null}  $filtros
     */
    public function paginar(array $filtros): LengthAwarePaginator
    {
        return MovimientoInventario::query()
            ->with([
                'lote:id,codigo_lote,producto_id',
                'producto:id,codigo,nombre',
                'usuario:id,name',
            ])
            ->when(
                $filtros['producto_id'] ?? null,
                fn ($q, $id) => $q->where('producto_id', $id)
            )
            ->when(
                $filtros['lote_id'] ?? null,
                fn ($q, $id) => $q->where('lote_id', $id)
            )
            ->when(
                $filtros['tipo'] ?? null,
                fn ($q, $t) => $q->where('tipo', $t)
            )
            ->when(
                $filtros['motivo'] ?? null,
                fn ($q, $m) => $q->where('motivo', $m)
            )
            ->when(
                $filtros['desde'] ?? null,
                fn ($q, $f) => $q->where('created_at', '>=', \Carbon\Carbon::parse($f)->startOfDay())
            )
            ->when(
                $filtros['hasta'] ?? null,
                fn ($q, $f) => $q->where('created_at', '<=', \Carbon\Carbon::parse($f)->endOfDay())
            )
            ->orderByDesc('created_at')
            ->paginate(config('dsalud.paginacion.por_pagina'))
            ->withQueryString();
    }

    /**
     * Kardex completo de un lote, ordenado cronológicamente.
     *
     * @return Collection<int, MovimientoInventario>
     */
    public function kardexDeLote(int $loteId): Collection
    {
        return MovimientoInventario::query()
            ->with(['usuario:id,name'])
            ->where('lote_id', $loteId)
            ->orderBy('created_at')
            ->get();
    }
}
