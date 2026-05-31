<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoCaja;
use App\Models\Caja;
use App\Models\Venta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio de la caja registradora (turno operativo).
 *
 * Reglas:
 *  - Un usuario solo puede tener UNA caja ABIERTA simultánea
 *  - El monto de apertura es declarado por el cajero
 *  - Al cerrar se calcula total_ventas del periodo, total_esperado y diferencia
 *  - Una caja CERRADA es terminal: no se reabre ni se modifica
 */
class CajaService
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    /**
     * Lista paginada de cajas con filtro opcional por usuario y estado.
     *
     * @param  array{user_id?: int|null, estado?: string|null}  $filtros
     */
    public function paginar(array $filtros): LengthAwarePaginator
    {
        return Caja::query()
            ->with(['cajero:id,name', 'cerradaPor:id,name'])
            ->when(
                $filtros['user_id'] ?? null,
                fn ($q, $id) => $q->where('user_id', $id)
            )
            ->when(
                $filtros['estado'] ?? null,
                fn ($q, $estado) => $q->where('estado', $estado)
            )
            ->orderByDesc('abierta_en')
            ->paginate(config('dsalud.paginacion.por_pagina'))
            ->withQueryString();
    }

    /**
     * Caja ABIERTA del usuario, o null si no tiene ninguna.
     * Usado por el POS para decidir si se puede vender.
     */
    public function cajaAbiertaDe(int $userId): ?Caja
    {
        return Caja::query()
            ->abierta()
            ->where('user_id', $userId)
            ->orderByDesc('abierta_en')
            ->first();
    }

    /**
     * Abre una caja para el usuario con un monto inicial.
     *
     * @throws \RuntimeException  Si ya tiene una caja abierta.
     */
    public function abrir(int $userId, float $montoApertura, ?string $observaciones = null): Caja
    {
        return DB::transaction(function () use ($userId, $montoApertura, $observaciones): Caja {
            // Bloqueo defensivo: si entre el chequeo y el insert otra request abre
            // una caja, el segundo intento queda fuera. SELECT...FOR UPDATE sobre
            // las cajas del usuario serializa este path.
            $existente = Caja::query()
                ->lockForUpdate()
                ->abierta()
                ->where('user_id', $userId)
                ->first();

            if ($existente !== null) {
                throw new \RuntimeException(
                    'Ya tienes una caja abierta. Ciérrala antes de abrir una nueva.',
                );
            }

            $caja = Caja::create([
                'user_id'        => $userId,
                'abierta_en'     => now(),
                'monto_apertura' => $montoApertura,
                'estado'         => EstadoCaja::ABIERTA,
                'observaciones'  => $observaciones,
            ]);

            $this->auditoria->registrar(
                'cajas',
                'abrir',
                "Caja #{$caja->id} abierta con S/ {$caja->monto_apertura}",
            );

            return $caja;
        });
    }

    /**
     * Cierra la caja calculando ventas del periodo, esperado y diferencia.
     *
     * @throws \RuntimeException  Si la caja ya está cerrada.
     */
    public function cerrar(Caja $caja, float $montoCierre, int $userId, ?string $observaciones = null): Caja
    {
        return DB::transaction(function () use ($caja, $montoCierre, $userId, $observaciones): Caja {
            $caja = Caja::lockForUpdate()->findOrFail($caja->id);

            if ($caja->estado === EstadoCaja::CERRADA) {
                throw new \RuntimeException('La caja ya está cerrada.');
            }

            // Suma de ventas COMPLETADAS del usuario en el periodo de la caja.
            // Las ventas ANULADAS no cuentan (su anulación neutralizó el ingreso).
            $totalVentas = (float) Venta::query()
                ->where('user_id', $caja->user_id)
                ->where('estado', Venta::ESTADO_COMPLETADA)
                ->where('created_at', '>=', $caja->abierta_en)
                ->where('created_at', '<=', now())
                ->sum('total');

            $totalEsperado = (float) $caja->monto_apertura + $totalVentas;
            $diferencia    = $montoCierre - $totalEsperado;

            $caja->update([
                'cerrada_en'     => now(),
                'cerrada_por'    => $userId,
                'monto_cierre'   => $montoCierre,
                'total_ventas'   => $totalVentas,
                'total_esperado' => $totalEsperado,
                'diferencia'     => $diferencia,
                'estado'         => EstadoCaja::CERRADA,
                // Concatenamos observaciones de cierre a las de apertura si hay.
                'observaciones'  => $observaciones !== null && $observaciones !== ''
                    ? trim(($caja->observaciones ?? '') . "\nCierre: " . $observaciones)
                    : $caja->observaciones,
            ]);

            $this->auditoria->registrar(
                'cajas',
                'cerrar',
                sprintf(
                    'Caja #%d cerrada — esperado S/ %.2f / declarado S/ %.2f / diferencia S/ %.2f',
                    $caja->id,
                    $totalEsperado,
                    $montoCierre,
                    $diferencia,
                ),
            );

            return $caja->fresh(['cajero', 'cerradaPor']);
        });
    }
}
