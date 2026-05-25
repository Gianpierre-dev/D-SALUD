<?php

namespace App\Services;

use App\Models\Boleta;
use App\Models\DetalleVenta;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio del módulo de ventas.
 *
 * Centraliza:
 *  - El proceso de venta con descuento FEFO (First Expired, First Out).
 *  - La generación de boleta correlativa (serie configurable).
 *  - La anulación con reposición de stock.
 *  - La paginación del historial y los datos auxiliares del POS.
 */
class VentaService
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    /**
     * Registra una venta nueva aplicando FEFO con bloqueo pesimista.
     *
     * Cada ítem descuenta stock de los lotes ordenados por fecha_vencimiento ASC.
     * Si un producto no tiene stock suficiente se lanza RuntimeException y
     * la transacción hace rollback automático.
     *
     * @param  array<int, array{producto_id: int, cantidad: int}>  $items
     * @throws \RuntimeException  Cuando el stock de un producto es insuficiente.
     */
    public function registrar(array $items, int $userId): Venta
    {
        return DB::transaction(function () use ($items, $userId): Venta {
            // 1. Crear la venta con total provisional.
            $venta = Venta::create([
                'user_id' => $userId,
                'total'   => 0,
                'estado'  => Venta::ESTADO_COMPLETADA,
            ]);

            $totalVenta = 0;

            foreach ($items as $item) {
                $productoId = (int) $item['producto_id'];
                $cantidadPendiente = (int) $item['cantidad'];

                /** @var Producto $producto */
                $producto = Producto::findOrFail($productoId);

                // 2. Lotes con stock, ordenados FEFO, bloqueados para esta transacción.
                $lotes = Lote::where('producto_id', $productoId)
                    ->where('stock', '>', 0)
                    ->orderBy('fecha_vencimiento', 'asc')
                    ->lockForUpdate()
                    ->get();

                // 3. Descontar stock lote a lote (FEFO).
                foreach ($lotes as $lote) {
                    if ($cantidadPendiente === 0) {
                        break;
                    }

                    $tomado = min($cantidadPendiente, $lote->stock);

                    $lote->stock -= $tomado;
                    $lote->save();

                    $subtotal = $tomado * (float) $producto->precio_venta;

                    DetalleVenta::create([
                        'venta_id'       => $venta->id,
                        'lote_id'        => $lote->id,
                        'producto_id'    => $productoId,
                        'cantidad'       => $tomado,
                        'precio_unitario' => $producto->precio_venta,
                        'subtotal'       => $subtotal,
                    ]);

                    $totalVenta      += $subtotal;
                    $cantidadPendiente -= $tomado;
                }

                // 4. Si sobra cantidad => stock insuficiente => rollback.
                if ($cantidadPendiente > 0) {
                    throw new \RuntimeException(
                        "Stock insuficiente para el producto {$producto->nombre}."
                    );
                }
            }

            // 5. Actualizar total de la venta.
            $venta->total = $totalVenta;
            $venta->save();

            // 6. Generar boleta correlativa con bloqueo para evitar duplicados.
            $serie  = config('dsalud.boleta.serie');
            $numero = (Boleta::where('serie', $serie)->lockForUpdate()->max('numero') ?? 0) + 1;

            $boleta = Boleta::create([
                'venta_id'      => $venta->id,
                'serie'         => $serie,
                'numero'        => $numero,
                'fecha_emision' => now(),
            ]);

            // 7. Auditoría.
            $this->auditoria->registrar(
                'ventas',
                'registrar',
                "Venta #{$venta->id} - Boleta {$boleta->numero_formateado} - Total S/ {$venta->total}"
            );

            return $venta->load('detalles.producto', 'boleta');
        });
    }

    /**
     * Anula una venta, repone stock en los lotes originales y registra auditoría.
     *
     * @throws \RuntimeException  Si la venta ya está anulada.
     */
    public function anular(Venta $venta, string $motivo, int $userId): void
    {
        DB::transaction(function () use ($venta, $motivo, $userId): void {
            if ($venta->estado === Venta::ESTADO_ANULADA) {
                throw new \RuntimeException('La venta ya está anulada.');
            }

            // Reponer stock en cada lote afectado.
            foreach ($venta->detalles as $detalle) {
                Lote::where('id', $detalle->lote_id)
                    ->increment('stock', $detalle->cantidad);
            }

            $venta->update([
                'estado'           => Venta::ESTADO_ANULADA,
                'motivo_anulacion' => $motivo,
                'anulada_por'      => $userId,
                'anulada_en'       => now(),
            ]);

            $this->auditoria->registrar(
                'ventas',
                'anular',
                "Venta #{$venta->id} anulada. Motivo: {$motivo}"
            );
        });
    }

    /**
     * Historial paginado de ventas con filtros opcionales.
     *
     * @param  array{fecha?: string|null, vendedor_id?: int|null, estado?: string|null}  $filtros
     */
    public function paginarHistorial(array $filtros): LengthAwarePaginator
    {
        return Venta::with(['vendedor', 'boleta'])
            ->when(
                $filtros['fecha'] ?? null,
                fn ($q, $fecha) => $q->whereDate('created_at', $fecha)
            )
            ->when(
                $filtros['vendedor_id'] ?? null,
                fn ($q, $id) => $q->where('user_id', $id)
            )
            ->when(
                $filtros['estado'] ?? null,
                fn ($q, $estado) => $q->where('estado', $estado)
            )
            ->orderByDesc('created_at')
            ->paginate(config('dsalud.paginacion.por_pagina'))
            ->withQueryString();
    }

    /**
     * Productos activos con stock disponible para el POS.
     * Devuelve id, codigo, nombre, precio_venta y stock_total calculado.
     */
    public function productosDisponibles(): Collection
    {
        return Producto::query()
            ->where('activo', true)
            ->withSum('lotes as stock_total', 'stock')
            ->having('stock_total', '>', 0)
            ->get(['id', 'codigo', 'nombre', 'precio_venta']);
    }

    /**
     * Usuarios que han realizado al menos una venta (para el filtro del historial).
     */
    public function vendedores(): Collection
    {
        return User::whereHas('ventas')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
