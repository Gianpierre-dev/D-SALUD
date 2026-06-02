<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sentido de un movimiento de efectivo de caja que no es una venta.
 */
enum TipoMovimientoCaja: string
{
    case INGRESO = 'INGRESO';
    case EGRESO  = 'EGRESO';

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
            self::INGRESO => 'Ingreso',
            self::EGRESO  => 'Egreso',
        };
    }
}
