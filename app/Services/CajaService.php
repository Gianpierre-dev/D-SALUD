<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoCaja;
use App\Enums\MedioPago;
use App\Enums\TipoMovimientoCaja;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Pago;
use App\Models\Venta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio de la caja registradora (turno operativo).
 *
 * Reglas:
 *  - Un usuario solo puede tener UNA caja ABIERTA simultánea
 *  - El monto de apertura es declarado por el cajero
 *  - El cuadre es sobre EFECTIVO FÍSICO: solo cuentan los pagos en efectivo
 *    de las ventas de esta caja, más los ingresos y menos los egresos manuales.
 *    Las ventas con tarjeta/Yape/Plin NO entran a la gaveta y por tanto no
 *    inflan el efectivo esperado.
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
     * Registra un ingreso o egreso de efectivo que no proviene de una venta
     * (retiro, pago a proveedor desde caja, fondo adicional, gasto menor).
     *
     * @throws \RuntimeException  Si la caja no está abierta o el monto es inválido.
     */
    public function registrarMovimiento(
        Caja $caja,
        TipoMovimientoCaja $tipo,
        float $monto,
        string $concepto,
        int $userId,
    ): MovimientoCaja {
        if ($monto <= 0) {
            throw new \RuntimeException('El monto del movimiento debe ser mayor a 0.');
        }

        return DB::transaction(function () use ($caja, $tipo, $monto, $concepto, $userId): MovimientoCaja {
            $caja = Caja::lockForUpdate()->findOrFail($caja->id);

            if ($caja->estado !== EstadoCaja::ABIERTA) {
                throw new \RuntimeException('No se pueden registrar movimientos en una caja cerrada.');
            }

            $movimiento = MovimientoCaja::create([
                'caja_id'  => $caja->id,
                'tipo'     => $tipo,
                'monto'    => round($monto, 2),
                'concepto' => $concepto,
                'user_id'  => $userId,
            ]);

            $this->auditoria->registrar(
                'cajas',
                'movimiento',
                sprintf('Caja #%d — %s S/ %.2f — %s', $caja->id, $tipo->value, $monto, $concepto),
            );

            return $movimiento;
        });
    }

    /**
     * Cierra la caja calculando el efectivo esperado en gaveta y la diferencia.
     *
     * Efectivo esperado = apertura
     *                   + ventas EN EFECTIVO de esta caja
     *                   + ingresos manuales
     *                   - egresos manuales
     *
     * `total_ventas` guarda el total de TODOS los medios (informativo); el
     * cuadre (`total_esperado`) es solo efectivo.
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

            $ventasEfectivo = $this->totalEfectivoVentas($caja);
            $totalVentas    = $this->totalVentas($caja);
            $ingresos       = $this->totalMovimientos($caja, TipoMovimientoCaja::INGRESO);
            $egresos        = $this->totalMovimientos($caja, TipoMovimientoCaja::EGRESO);

            $efectivoEsperado = (float) $caja->monto_apertura + $ventasEfectivo + $ingresos - $egresos;
            $diferencia       = $montoCierre - $efectivoEsperado;

            $caja->update([
                'cerrada_en'     => now(),
                'cerrada_por'    => $userId,
                'monto_cierre'   => $montoCierre,
                'total_ventas'   => $totalVentas,
                'total_esperado' => $efectivoEsperado,
                'diferencia'     => $diferencia,
                'estado'         => EstadoCaja::CERRADA,
                'observaciones'  => $observaciones !== null && $observaciones !== ''
                    ? trim(($caja->observaciones ?? '') . "\nCierre: " . $observaciones)
                    : $caja->observaciones,
            ]);

            $this->auditoria->registrar(
                'cajas',
                'cerrar',
                sprintf(
                    'Caja #%d cerrada — efectivo esperado S/ %.2f / declarado S/ %.2f / diferencia S/ %.2f',
                    $caja->id,
                    $efectivoEsperado,
                    $montoCierre,
                    $diferencia,
                ),
            );

            return $caja->fresh(['cajero', 'cerradaPor']);
        });
    }

    /**
     * Desglose de ventas de la caja por medio de pago.
     * Se usa en el detalle de caja y en el reporte Z.
     *
     * @return array<string, float>  ['EFECTIVO' => 120.0, 'YAPE' => 50.0, ...]
     */
    public function desglosePorMedio(Caja $caja): array
    {
        $base = array_fill_keys(MedioPago::values(), 0.0);

        $sumas = Pago::query()
            ->join('ventas', 'pagos.venta_id', '=', 'ventas.id')
            ->where('ventas.caja_id', $caja->id)
            ->where('ventas.estado', Venta::ESTADO_COMPLETADA)
            ->groupBy('pagos.medio_pago')
            ->selectRaw('pagos.medio_pago, SUM(pagos.monto) as total')
            ->pluck('total', 'medio_pago')
            ->map(fn ($t) => (float) $t)
            ->all();

        return array_merge($base, $sumas);
    }

    /**
     * Total en efectivo de las ventas COMPLETADAS de esta caja.
     */
    private function totalEfectivoVentas(Caja $caja): float
    {
        return (float) Pago::query()
            ->join('ventas', 'pagos.venta_id', '=', 'ventas.id')
            ->where('ventas.caja_id', $caja->id)
            ->where('ventas.estado', Venta::ESTADO_COMPLETADA)
            ->where('pagos.medio_pago', MedioPago::EFECTIVO->value)
            ->sum('pagos.monto');
    }

    /**
     * Total de ventas COMPLETADAS de esta caja (todos los medios).
     */
    private function totalVentas(Caja $caja): float
    {
        return (float) Venta::query()
            ->where('caja_id', $caja->id)
            ->where('estado', Venta::ESTADO_COMPLETADA)
            ->sum('total');
    }

    private function totalMovimientos(Caja $caja, TipoMovimientoCaja $tipo): float
    {
        return (float) MovimientoCaja::query()
            ->where('caja_id', $caja->id)
            ->where('tipo', $tipo->value)
            ->sum('monto');
    }
}
