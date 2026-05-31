<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de documento de identidad/tributario soportados en el módulo de clientes.
 * Mantiene los valores centralizados y evita strings mágicos por el código.
 */
enum TipoDocumento: string
{
    case DNI = 'DNI';
    case RUC = 'RUC';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $tipo): string => $tipo->value, self::cases());
    }

    /**
     * Longitud exacta esperada del número de documento por tipo.
     */
    public function longitud(): int
    {
        return match ($this) {
            self::DNI => 8,
            self::RUC => 11,
        };
    }

    /**
     * Etiqueta legible para mostrar en UI.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::DNI => 'DNI',
            self::RUC => 'RUC',
        };
    }
}
