<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoCompra;
use App\Enums\MotivoMovimiento;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Lote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio del módulo de Compras.
 *
 * Mantiene el ciclo:
 *   crear → (opcional) actualizar/anular → recibir → kardex + lotes
 *
 * Toda transición de estado es transaccional y validada contra
 * EstadoCompra::esTerminal(). El alta de stock pasa por
 * MovimientoInventarioService para reutilizar el kardex.
 */
class CompraService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
        private readonly MovimientoInventarioService $movimientos,
    ) {
    }

    public function paginar(array $filtros): LengthAwarePaginator
    {
        return Compra::query()
            ->with(['proveedor:id,razon_social,ruc', 'registradaPor:id,name'])
            ->when(
                $filtros['estado'] ?? null,
                fn ($q, $estado) => $q->where('estado', $estado)
            )
            ->when(
                $filtros['proveedor_id'] ?? null,
                fn ($q, $id) => $q->where('proveedor_id', $id)
            )
            ->when(
                $filtros['fecha'] ?? null,
                fn ($q, $fecha) => $q->whereDate('fecha_compra', $fecha)
            )
            ->orderByDesc('created_at')
            ->paginate(config('dsalud.paginacion.por_pagina'))
            ->withQueryString();
    }

    /**
     * Crea una compra en estado PENDIENTE con sus detalles.
     *
     * @param  array{proveedor_id: int, fecha_compra: string, observaciones?: string|null, items: array<int, array{producto_id: int, cantidad: int, precio_unitario: string|float, codigo_lote: string, fecha_vencimiento: string}>}  $datos
     */
    public function crear(array $datos, int $userId): Compra
    {
        return DB::transaction(function () use ($datos, $userId): Compra {
            $numero = $this->siguienteCorrelativo();

            $total = 0.0;
            foreach ($datos['items'] as $item) {
                $total += (float) $item['cantidad'] * (float) $item['precio_unitario'];
            }

            $compra = Compra::create([
                'serie'         => 'OC',
                'numero'        => $numero,
                'proveedor_id'  => $datos['proveedor_id'],
                'user_id'       => $userId,
                'fecha_compra'  => $datos['fecha_compra'],
                'estado'        => EstadoCompra::PENDIENTE,
                'total'         => $total,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            foreach ($datos['items'] as $item) {
                DetalleCompra::create([
                    'compra_id'         => $compra->id,
                    'producto_id'       => $item['producto_id'],
                    'cantidad'          => $item['cantidad'],
                    'precio_unitario'   => $item['precio_unitario'],
                    'subtotal'          => (float) $item['cantidad'] * (float) $item['precio_unitario'],
                    'codigo_lote'       => $item['codigo_lote'],
                    'fecha_vencimiento' => $item['fecha_vencimiento'],
                ]);
            }

            $this->auditoria->registrar(
                'compras',
                'crear',
                "Compra {$compra->numero_formateado} a proveedor #{$compra->proveedor_id} — Total S/ {$compra->total}",
            );

            return $compra->load('detalles.producto', 'proveedor');
        });
    }

    /**
     * Actualiza una compra. Solo permitido si está PENDIENTE.
     *
     * @param  array{proveedor_id: int, fecha_compra: string, observaciones?: string|null, items: array<int, array{producto_id: int, cantidad: int, precio_unitario: string|float, codigo_lote: string, fecha_vencimiento: string}>}  $datos
     */
    public function actualizar(Compra $compra, array $datos): Compra
    {
        if ($compra->estado !== EstadoCompra::PENDIENTE) {
            throw new \RuntimeException(
                "La compra {$compra->numero_formateado} no se puede modificar porque está en estado {$compra->estado->etiqueta()}.",
            );
        }

        return DB::transaction(function () use ($compra, $datos): Compra {
            $compra = Compra::lockForUpdate()->findOrFail($compra->id);

            // Recalcular total y reemplazar líneas.
            $total = 0.0;
            foreach ($datos['items'] as $item) {
                $total += (float) $item['cantidad'] * (float) $item['precio_unitario'];
            }

            $compra->update([
                'proveedor_id'  => $datos['proveedor_id'],
                'fecha_compra'  => $datos['fecha_compra'],
                'observaciones' => $datos['observaciones'] ?? null,
                'total'         => $total,
            ]);

            $compra->detalles()->delete();
            foreach ($datos['items'] as $item) {
                DetalleCompra::create([
                    'compra_id'         => $compra->id,
                    'producto_id'       => $item['producto_id'],
                    'cantidad'          => $item['cantidad'],
                    'precio_unitario'   => $item['precio_unitario'],
                    'subtotal'          => (float) $item['cantidad'] * (float) $item['precio_unitario'],
                    'codigo_lote'       => $item['codigo_lote'],
                    'fecha_vencimiento' => $item['fecha_vencimiento'],
                ]);
            }

            $this->auditoria->registrar(
                'compras',
                'actualizar',
                "Compra {$compra->numero_formateado} editada — Total S/ {$compra->total}",
            );

            return $compra->load('detalles.producto', 'proveedor');
        });
    }

    /**
     * Recepciona la mercadería: por cada detalle crea un lote nuevo y
     * registra el movimiento ENTRADA + motivo=COMPRA en el kardex.
     * Operación atómica: si una sola línea falla, todo hace rollback.
     */
    public function recibir(Compra $compra, int $userId): Compra
    {
        if ($compra->estado !== EstadoCompra::PENDIENTE) {
            throw new \RuntimeException(
                "La compra {$compra->numero_formateado} no se puede recibir porque está en estado {$compra->estado->etiqueta()}.",
            );
        }

        return DB::transaction(function () use ($compra, $userId): Compra {
            $compra = Compra::lockForUpdate()->findOrFail($compra->id);
            $compra->load('detalles');

            // Re-chequeo del estado bajo lock — evita doble recepción en carrera.
            if ($compra->estado !== EstadoCompra::PENDIENTE) {
                throw new \RuntimeException('La compra ya fue procesada por otro usuario.');
            }

            foreach ($compra->detalles as $detalle) {
                $lote = Lote::create([
                    'producto_id'       => $detalle->producto_id,
                    'proveedor_id'      => $compra->proveedor_id,
                    'codigo_lote'       => $detalle->codigo_lote,
                    'fecha_vencimiento' => $detalle->fecha_vencimiento,
                    'stock'             => 0,
                    'precio_compra'     => $detalle->precio_unitario,
                ]);

                $this->movimientos->registrarEntrada(
                    $lote,
                    MotivoMovimiento::COMPRA,
                    (int) $detalle->cantidad,
                    "Recepción de compra {$compra->numero_formateado}",
                    ['tipo' => 'compra', 'id' => $compra->id],
                    $userId,
                );
            }

            $compra->update([
                'estado'       => EstadoCompra::RECIBIDA,
                'recibida_en'  => now(),
                'recibida_por' => $userId,
            ]);

            $this->auditoria->registrar(
                'compras',
                'recibir',
                "Compra {$compra->numero_formateado} recibida — {$compra->detalles->count()} lotes generados",
            );

            return $compra->load('detalles.producto', 'proveedor', 'recibidaPor');
        });
    }

    /**
     * Anula una compra. Solo si está PENDIENTE.
     * No genera movimientos de inventario (la PENDIENTE nunca afectó stock).
     */
    public function anular(Compra $compra, string $motivo, int $userId): void
    {
        DB::transaction(function () use ($compra, $motivo, $userId): void {
            $compra = Compra::lockForUpdate()->findOrFail($compra->id);

            if ($compra->estado !== EstadoCompra::PENDIENTE) {
                throw new \RuntimeException(
                    "La compra {$compra->numero_formateado} no se puede anular porque está en estado {$compra->estado->etiqueta()}.",
                );
            }

            $compra->update([
                'estado'           => EstadoCompra::ANULADA,
                'anulada_en'       => now(),
                'anulada_por'      => $userId,
                'motivo_anulacion' => $motivo,
            ]);

            $this->auditoria->registrar(
                'compras',
                'anular',
                "Compra {$compra->numero_formateado} anulada. Motivo: {$motivo}",
            );
        });
    }

    /**
     * Avanza el correlativo de la serie 'OC' bajo lockForUpdate para
     * evitar duplicados cuando dos admins crean compras concurrentemente.
     */
    private function siguienteCorrelativo(string $serie = 'OC'): int
    {
        DB::table('secuencias_compra')->updateOrInsert(
            ['serie' => $serie],
            ['updated_at' => now()],
        );

        $ultimo = (int) DB::table('secuencias_compra')
            ->where('serie', $serie)
            ->lockForUpdate()
            ->value('ultimo_numero');

        $numero = $ultimo + 1;

        DB::table('secuencias_compra')
            ->where('serie', $serie)
            ->update(['ultimo_numero' => $numero, 'updated_at' => now()]);

        return $numero;
    }
}
