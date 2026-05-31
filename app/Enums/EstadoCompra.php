<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados del ciclo de vida de una orden de compra.
 *
 * Transiciones legales:
 *   PENDIENTE → RECIBIDA  (al recibir mercadería: genera lotes + kardex)
 *   PENDIENTE → ANULADA   (al anular orden pendiente)
 *
 * RECIBIDA y ANULADA son terminales: no se pueden modificar.
 * Para revertir una RECIBIDA usar DEVOLUCION_PROVEEDOR en el kardex.
 */
enum EstadoCompra: string
{
    case PENDIENTE = 'PENDIENTE';
    case RECIBIDA  = 'RECIBIDA';
    case ANULADA   = 'ANULADA';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $e): string => $e->value, self::cases());
    }

    public function esTerminal(): bool
    {
        return $this === self::RECIBIDA || $this === self::ANULADA;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::RECIBIDA  => 'Recibida',
            self::ANULADA   => 'Anulada',
        };
    }
}
