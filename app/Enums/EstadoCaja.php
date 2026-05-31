<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado del turno de caja.
 * Solo se puede vender mientras la caja del usuario esté ABIERTA.
 */
enum EstadoCaja: string
{
    case ABIERTA = 'ABIERTA';
    case CERRADA = 'CERRADA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ABIERTA => 'Abierta',
            self::CERRADA => 'Cerrada',
        };
    }
}
