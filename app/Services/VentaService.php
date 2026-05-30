<?php

declare(strict_types=1);

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
use Illuminate\Support\Facades\Log;

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

                /** @var Producto|null $producto */
                $producto = Producto::find($productoId);

                // Solo se venden productos activos.
                if ($producto === null || ! $producto->activo) {
                    Log::warning('Venta rechazada: producto inactivo o inexistente', [
                        'user_id' => $userId,
                        'producto_id' => $productoId,
                    ]);
                    throw new \RuntimeException(
                        'El producto seleccionado no está disponible para la venta.'
                    );
                }

                // 2. Lotes con stock y NO vencidos, ordenados FEFO, bloqueados para esta transacción.
                //    Excluir vencidos es crítico en una botica: nunca dispensar producto vencido.
                $lotes = Lote::where('producto_id', $productoId)
                    ->where('stock', '>', 0)
                    ->where('fecha_vencimiento', '>=', now()->toDateString())
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
                    Log::warning('Venta rechazada: stock insuficiente', [
                        'user_id' => $userId,
                        'producto_id' => $productoId,
                        'producto' => $producto->nombre,
                        'cantidad_pendiente' => $cantidadPendiente,
                    ]);
                    throw new \RuntimeException(
                        "Stock insuficiente para el producto {$producto->nombre}."
                    );
                }
            }

            // 5. Actualizar total de la venta.
            $venta->total = $totalVenta;
            $venta->save();

            // 6. Generar boleta correlativa.
            //    Se usa la tabla `secuencias_boleta` con lockForUpdate sobre la fila
            //    de la serie: dos cajas concurrentes serializan acceso a esa fila y
            //    nunca obtienen el mismo número (a diferencia de MAX(numero)+1, que
             //   sufre race condition bajo READ COMMITTED).
            $serie = config('dsalud.boleta.serie');

            DB::table('secuencias_boleta')->updateOrInsert(
                ['serie' => $serie],
                ['updated_at' => now()],
            );

            $ultimo = (int) DB::table('secuencias_boleta')
                ->where('serie', $serie)
                ->lockForUpdate()
                ->value('ultimo_numero');

            $numero = $ultimo + 1;

            DB::table('secuencias_boleta')
                ->where('serie', $serie)
                ->update(['ultimo_numero' => $numero, 'updated_at' => now()]);

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
            // Recargar con bloqueo pesimista para evitar anulaciones concurrentes
            // que repongan el stock por duplicado.
            $venta = Venta::lockForUpdate()->findOrFail($venta->id);
            $venta->load('detalles');

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
                // whereBetween sargable: usa el índice (ventas_estado_created_idx).
                // whereDate envuelve la columna en DATE() e impide el uso del índice.
                fn ($q, $fecha) => $q->whereBetween('created_at', [
                    \Carbon\Carbon::parse($fecha)->startOfDay(),
                    \Carbon\Carbon::parse($fecha)->endOfDay(),
                ])
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
        // El stock disponible para el POS solo cuenta lotes NO vencidos con stock,
        // alineado con el FEFO de registrar(). Sin este filtro, el cajero veía
        // productos "con stock" cuyos lotes estaban todos vencidos, y la venta
        // fallaba con RuntimeException "Stock insuficiente".
        $stockVigente = DB::table('lotes')
            ->select('producto_id', DB::raw('COALESCE(SUM(stock), 0) as stock_total'))
            ->where('stock', '>', 0)
            ->where('fecha_vencimiento', '>=', now()->toDateString())
            ->groupBy('producto_id');

        return Producto::query()
            ->where('productos.activo', true)
            ->joinSub($stockVigente, 'agg', 'agg.producto_id', '=', 'productos.id')
            ->select('productos.id', 'productos.codigo', 'productos.nombre', 'productos.precio_venta')
            ->selectRaw('agg.stock_total as stock_total')
            ->orderBy('productos.nombre')
            ->get();
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
