<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Clasificación de origen de un movimiento de inventario.
 * El tipo (ENTRADA/SALIDA) se deriva del motivo via tipo().
 */
enum MotivoMovimiento: string
{
    // Automáticos (los registra el flujo de venta)
    case VENTA               = 'VENTA';
    case ANULACION_VENTA     = 'ANULACION_VENTA';

    // Manuales (los registra el Administrador desde el módulo)
    case INVENTARIO_INICIAL  = 'INVENTARIO_INICIAL';
    case AJUSTE_POSITIVO     = 'AJUSTE_POSITIVO';
    case AJUSTE_NEGATIVO     = 'AJUSTE_NEGATIVO';
    case MERMA               = 'MERMA';
    case VENCIMIENTO         = 'VENCIMIENTO';
    // Nota: D'Salud NO acepta devoluciones de cliente. Si la política cambia
    // en el futuro, agregar DEVOLUCION_CLIENTE acá + en tipo() + etiqueta() +
    // manuales(). La ANULACION_VENTA queda como mecanismo de rectificación
    // de errores de registro (no es una devolución física).
    case DEVOLUCION_PROVEEDOR = 'DEVOLUCION_PROVEEDOR';

    // Reservado para el futuro módulo de Compras
    case COMPRA              = 'COMPRA';

    /**
     * Indica si este motivo SIEMPRE genera una ENTRADA o una SALIDA.
     */
    public function tipo(): TipoMovimiento
    {
        return match ($this) {
            self::COMPRA,
            self::ANULACION_VENTA,
            self::INVENTARIO_INICIAL,
            self::AJUSTE_POSITIVO => TipoMovimiento::ENTRADA,

            self::VENTA,
            self::AJUSTE_NEGATIVO,
            self::MERMA,
            self::VENCIMIENTO,
            self::DEVOLUCION_PROVEEDOR => TipoMovimiento::SALIDA,
        };
    }

    /**
     * Etiqueta legible para UI.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::VENTA               => 'Venta',
            self::ANULACION_VENTA     => 'Anulación de venta',
            self::COMPRA              => 'Compra',
            self::INVENTARIO_INICIAL  => 'Inventario inicial',
            self::AJUSTE_POSITIVO     => 'Ajuste positivo',
            self::AJUSTE_NEGATIVO     => 'Ajuste negativo',
            self::MERMA               => 'Merma',
            self::VENCIMIENTO         => 'Vencimiento',
            self::DEVOLUCION_PROVEEDOR => 'Devolución a proveedor',
        };
    }

    /**
     * Motivos que el Administrador puede elegir manualmente desde el módulo.
     * Los automáticos (VENTA, ANULACION_VENTA, COMPRA) NO aparecen acá.
     *
     * @return array<int, self>
     */
    public static function manuales(): array
    {
        return [
            self::INVENTARIO_INICIAL,
            self::AJUSTE_POSITIVO,
            self::AJUSTE_NEGATIVO,
            self::MERMA,
            self::VENCIMIENTO,
            self::DEVOLUCION_PROVEEDOR,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function manualesValues(): array
    {
        return array_map(static fn (self $m): string => $m->value, self::manuales());
    }
}
