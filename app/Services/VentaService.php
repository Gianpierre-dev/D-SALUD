<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoCaja;
use App\Enums\MedioPago;
use App\Enums\MotivoMovimiento;
use App\Enums\TipoComprobante;
use App\Models\Boleta;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Lote;
use App\Models\Pago;
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
    public function __construct(
        private readonly AuditoriaService $auditoria,
        private readonly MovimientoInventarioService $movimientos,
    ) {
    }

    /**
     * Registra una venta nueva aplicando FEFO con bloqueo pesimista.
     *
     * Cada ítem descuenta stock de los lotes ordenados por fecha_vencimiento ASC.
     * Si un producto no tiene stock suficiente se lanza RuntimeException y
     * la transacción hace rollback automático.
     *
     * @param  array<int, array{producto_id: int, cantidad: int}>  $items
     * @param  int|null  $clienteId      Cliente vinculado (null = consumidor final).
     * @param  array<int, array{medio_pago: string, monto: float|string}>  $pagos
     *         Desglose de pago. Si viene vacío, se asume un único pago EFECTIVO
     *         por el total (comportamiento legacy seguro para tests de dominio).
     * @param  int|null   $cajaId        Caja del turno (vínculo directo para el cuadre).
     * @param  float|null $montoRecibido Efectivo entregado por el cliente (para vuelto).
     * @param  string|null $tipoComprobante  BOLETA (default) o FACTURA (exige RUC).
     * @throws \RuntimeException  Stock insuficiente, pagos que no cubren el total,
     *                            factura sin RUC de cliente o caja no ABIERTA.
     */
    public function registrar(
        array $items,
        int $userId,
        ?int $clienteId = null,
        array $pagos = [],
        ?int $cajaId = null,
        ?float $montoRecibido = null,
        ?string $tipoComprobante = null,
    ): Venta {
        $tipo = $tipoComprobante !== null
            ? TipoComprobante::from($tipoComprobante)
            : TipoComprobante::BOLETA;

        // Una factura exige cliente con RUC: validar ANTES de tocar stock.
        $this->validarComprobante($tipo, $clienteId);

        return DB::transaction(function () use ($items, $userId, $clienteId, $pagos, $cajaId, $montoRecibido, $tipo): Venta {
            // Si la venta se vincula a una caja, bloquear su fila y exigir que
            // esté ABIERTA. Esto serializa registrar() contra cerrar() sobre la
            // misma caja: evita que una venta entre a una caja recién cerrada y
            // quede fuera del cuadre (descuadre silencioso de efectivo).
            if ($cajaId !== null) {
                $caja = Caja::lockForUpdate()->find($cajaId);

                if ($caja === null) {
                    throw new \RuntimeException('La caja indicada para la venta no existe.');
                }

                if ($caja->estado !== EstadoCaja::ABIERTA) {
                    throw new \RuntimeException(
                        'No se puede registrar la venta: la caja del turno está '
                        . "{$caja->estado->etiqueta()}."
                    );
                }
            }

            $venta = Venta::create([
                'user_id'    => $userId,
                'cliente_id' => $clienteId,
                'caja_id'    => $cajaId,
                'total'      => 0,
                'estado'     => Venta::ESTADO_COMPLETADA,
            ]);

            $total = 0.0;
            $montoGravadoConIgv = 0.0; // porción afecta a IGV (precio ya incluye IGV)
            foreach ($items as $item) {
                $linea = $this->procesarItemDeVenta($venta, $item, $userId);
                $total += $linea['subtotal'];
                $montoGravadoConIgv += $linea['gravado'];
            }

            // En Perú el precio al público incluye IGV: se desglosa hacia atrás.
            $tasa = (float) config('dsalud.igv.tasa');
            $baseGravada = $tasa > 0 ? round($montoGravadoConIgv / (1 + $tasa), 2) : $montoGravadoConIgv;
            $igv         = round($montoGravadoConIgv - $baseGravada, 2);

            $venta->total    = round($total, 2);
            $venta->subtotal = $baseGravada; // op. gravada (base imponible)
            $venta->igv      = $igv;

            $this->registrarPagos($venta, $total, $pagos, $montoRecibido);

            $venta->save();

            $comprobante = $this->generarComprobante($venta, $tipo);

            $this->auditoria->registrar(
                'ventas',
                'registrar',
                "Venta #{$venta->id} - {$tipo->etiqueta()} {$comprobante->numero_formateado} - Total S/ {$venta->total}"
            );

            return $venta->load('detalles.producto', 'boleta', 'cliente', 'pagos');
        });
    }

    /**
     * Valida la coherencia del comprobante con el cliente.
     * Una factura requiere cliente con documento RUC.
     *
     * @throws \RuntimeException
     */
    private function validarComprobante(TipoComprobante $tipo, ?int $clienteId): void
    {
        if (! $tipo->requiereRuc()) {
            return;
        }

        $cliente = $clienteId !== null ? Cliente::find($clienteId) : null;

        if ($cliente === null || $cliente->tipo_documento->value !== 'RUC') {
            throw new \RuntimeException(
                'Para emitir una factura el cliente debe estar registrado con RUC.'
            );
        }
    }

    /**
     * Persiste el desglose de pago y calcula el vuelto del efectivo.
     *
     * Reglas:
     *  - La suma de los montos aplicados debe igualar el total (tolerancia 1 céntimo).
     *  - Si no se envían pagos, se crea un único pago EFECTIVO por el total.
     *  - El vuelto solo aplica al componente efectivo: si el cliente entregó
     *    más efectivo del aplicado, la diferencia es el vuelto.
     *
     * @param  array<int, array{medio_pago: string, monto: float|string}>  $pagos
     * @throws \RuntimeException  Si la suma de pagos no cubre el total.
     */
    private function registrarPagos(Venta $venta, float $total, array $pagos, ?float $montoRecibido): void
    {
        // Legacy / consumidor final sin desglose: todo efectivo.
        if ($pagos === []) {
            $pagos = [['medio_pago' => MedioPago::EFECTIVO->value, 'monto' => $total]];
        }

        $sumaPagos     = 0.0;
        $montoEfectivo = 0.0;

        foreach ($pagos as $pago) {
            $monto = round((float) $pago['monto'], 2);
            if ($monto <= 0) {
                throw new \RuntimeException('El monto de cada pago debe ser mayor a 0.');
            }

            $medio = MedioPago::from($pago['medio_pago']);
            $sumaPagos += $monto;
            if ($medio->esEfectivo()) {
                $montoEfectivo += $monto;
            }

            Pago::create([
                'venta_id'   => $venta->id,
                'medio_pago' => $medio,
                'monto'      => $monto,
            ]);
        }

        // La suma de pagos debe cubrir exactamente el total (tolerancia centavo
        // por redondeo de float).
        if (abs($sumaPagos - round($total, 2)) > 0.01) {
            throw new \RuntimeException(
                sprintf('Los pagos (S/ %.2f) no coinciden con el total de la venta (S/ %.2f).', $sumaPagos, $total),
            );
        }

        // Vuelto: solo si hubo efectivo y el cliente entregó más de lo aplicado.
        if ($montoRecibido !== null && $montoEfectivo > 0) {
            $venta->monto_recibido = round($montoRecibido, 2);
            $venta->vuelto = max(0, round($montoRecibido - $montoEfectivo, 2));
        }
    }

    /**
     * Procesa un ítem del POS: valida el producto, descuenta el stock por FEFO
     * (excluyendo lotes vencidos) y crea las líneas de detalle correspondientes.
     *
     * @param  array{producto_id: int|string, cantidad: int|string}  $item
     * @return array{subtotal: float, gravado: float}  Subtotal de la línea y la
     *         porción afecta a IGV (igual al subtotal si el producto es gravado,
     *         0 si está exonerado).
     * @throws \RuntimeException  Producto inactivo / stock insuficiente.
     */
    private function procesarItemDeVenta(Venta $venta, array $item, int $userId): array
    {
        $productoId = (int) $item['producto_id'];
        $cantidadPendiente = (int) $item['cantidad'];

        $producto = Producto::find($productoId);

        if ($producto === null || ! $producto->activo) {
            Log::warning('Venta rechazada: producto inactivo o inexistente', [
                'user_id' => $userId,
                'producto_id' => $productoId,
            ]);
            throw new \RuntimeException(
                'El producto seleccionado no está disponible para la venta.'
            );
        }

        $lotes = $this->lotesVigentesParaFEFO($productoId);

        $subtotal = 0.0;
        foreach ($lotes as $lote) {
            if ($cantidadPendiente === 0) {
                break;
            }

            $tomado = min($cantidadPendiente, $lote->stock);

            // Toda mutación de stock pasa por el kardex: además de descontar,
            // genera la fila de SALIDA con motivo=VENTA y referencia=venta.id.
            // Si llegara a quedar negativo (imposible bajo lockForUpdate), el
            // service lanza excepción y la transacción externa hace rollback.
            $this->movimientos->registrarSalida(
                $lote,
                MotivoMovimiento::VENTA,
                $tomado,
                null,
                ['tipo' => 'venta', 'id' => $venta->id],
                $userId,
            );

            $aporte = $tomado * (float) $producto->precio_venta;

            DetalleVenta::create([
                'venta_id'        => $venta->id,
                'lote_id'         => $lote->id,
                'producto_id'     => $productoId,
                'cantidad'        => $tomado,
                'precio_unitario' => $producto->precio_venta,
                'subtotal'        => $aporte,
            ]);

            $subtotal           += $aporte;
            $cantidadPendiente  -= $tomado;
        }

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

        return [
            'subtotal' => $subtotal,
            // Si el producto está afecto a IGV, todo el subtotal es base gravada
            // (con IGV incluido); si está exonerado, no aporta IGV.
            'gravado'  => $producto->afecto_igv ? $subtotal : 0.0,
        ];
    }

    /**
     * Lotes con stock y NO vencidos para el producto, ordenados FEFO y bloqueados
     * para la transacción actual. Excluir vencidos es crítico: nunca dispensar
     * producto vencido en una botica.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Lote>
     */
    private function lotesVigentesParaFEFO(int $productoId): \Illuminate\Database\Eloquent\Collection
    {
        return Lote::where('producto_id', $productoId)
            ->where('stock', '>', 0)
            ->where('fecha_vencimiento', '>=', now()->toDateString())
            ->orderBy('fecha_vencimiento', 'asc')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Genera el comprobante correlativo (boleta o factura) usando la tabla
     * `secuencias_boleta` con lockForUpdate sobre la fila de la serie. La serie
     * depende del tipo (B001 boleta / F001 factura), de modo que cada tipo lleva
     * su propio correlativo independiente y serializado entre cajas concurrentes.
     */
    private function generarComprobante(Venta $venta, TipoComprobante $tipo): Boleta
    {
        $serie = $tipo->serie();

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

        return Boleta::create([
            'venta_id'         => $venta->id,
            'tipo_comprobante' => $tipo,
            'serie'            => $serie,
            'numero'           => $numero,
            'fecha_emision'    => now(),
        ]);
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

            // Reponer stock en cada lote afectado a través del kardex:
            // cada reposición queda como ENTRADA con motivo=ANULACION_VENTA
            // y referencia a la venta original. Esto deja trazabilidad
            // bidireccional (la venta y su reverso son visibles en el kardex).
            foreach ($venta->detalles as $detalle) {
                $lote = Lote::findOrFail($detalle->lote_id);
                $this->movimientos->registrarEntrada(
                    $lote,
                    MotivoMovimiento::ANULACION_VENTA,
                    (int) $detalle->cantidad,
                    "Reposición por anulación de venta #{$venta->id}",
                    ['tipo' => 'venta', 'id' => $venta->id],
                    $userId,
                );
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
     * @param  array{fecha?: string|null, vendedor_id?: int|null, estado?: string|null, cliente_id?: int|null}  $filtros
     */
    public function paginarHistorial(array $filtros): LengthAwarePaginator
    {
        return Venta::with(['vendedor', 'boleta', 'cliente:id,tipo_documento,numero_documento,nombre'])
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
                $filtros['cliente_id'] ?? null,
                fn ($q, $id) => $q->where('cliente_id', $id)
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
