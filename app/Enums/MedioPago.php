<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Medios de pago aceptados en el POS.
 * Solo EFECTIVO afecta el efectivo físico de la gaveta para el cuadre.
 */
enum MedioPago: string
{
    case EFECTIVO      = 'EFECTIVO';
    case TARJETA       = 'TARJETA';
    case YAPE          = 'YAPE';
    case PLIN          = 'PLIN';
    case TRANSFERENCIA = 'TRANSFERENCIA';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $m): string => $m->value, self::cases());
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::EFECTIVO      => 'Efectivo',
            self::TARJETA       => 'Tarjeta',
            self::YAPE          => 'Yape',
            self::PLIN          => 'Plin',
            self::TRANSFERENCIA => 'Transferencia',
        };
    }

    /**
     * ¿Este medio entra como efectivo físico a la gaveta?
     * Solo el efectivo afecta el conteo manual del cierre de caja.
     */
    public function esEfectivo(): bool
    {
        return $this === self::EFECTIVO;
    }
}
