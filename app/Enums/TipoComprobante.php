<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de comprobante de pago emitido por la venta.
 *
 *  - BOLETA: consumidor final, no requiere RUC.
 *  - FACTURA: requiere que el cliente tenga RUC (cliente empresa).
 */
enum TipoComprobante: string
{
    case BOLETA  = 'BOLETA';
    case FACTURA = 'FACTURA';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::BOLETA  => 'Boleta de venta',
            self::FACTURA => 'Factura',
        };
    }

    /**
     * Serie correlativa configurada para este tipo de comprobante.
     */
    public function serie(): string
    {
        return match ($this) {
            self::BOLETA  => (string) config('dsalud.comprobante.serie_boleta'),
            self::FACTURA => (string) config('dsalud.comprobante.serie_factura'),
        };
    }

    /**
     * ¿Este tipo de comprobante exige RUC del cliente?
     */
    public function requiereRuc(): bool
    {
        return $this === self::FACTURA;
    }
}
