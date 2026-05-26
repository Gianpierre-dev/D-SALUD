<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles del sistema. Centraliza los nombres de rol para evitar
 * strings mágicos repartidos por controladores, servicios y seeders.
 */
enum Rol: string
{
    case ADMINISTRADOR = 'Administrador';
    case VENDEDOR = 'Vendedor';

    /**
     * Nombres de todos los roles del sistema.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $rol): string => $rol->value, self::cases());
    }
}
