<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sentido del movimiento de inventario.
 * ENTRADA suma stock al lote; SALIDA lo resta.
 */
enum TipoMovimiento: string
{
    case ENTRADA = 'ENTRADA';
    case SALIDA = 'SALIDA';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $tipo): string => $tipo->value, self::cases());
    }
}
