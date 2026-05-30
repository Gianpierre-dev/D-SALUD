<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Helper para prevenir CSV/Formula Injection en archivos Excel/CSV exportados.
 *
 * Excel interpreta como fórmula cualquier celda que empiece con =, +, -, @,
 * tabulación o carriage return. Un atacante que controle un campo de texto
 * (p. ej. el nombre de un usuario o el detalle de auditoría) podría inyectar
 * una fórmula como `=cmd|'/c calc.exe'!A0` que se ejecuta cuando otro usuario
 * abre el reporte descargado.
 *
 * Referencia: https://owasp.org/www-community/attacks/CSV_Injection
 */
final class CsvSafe
{
    private const CARACTERES_PELIGROSOS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Antepone un apóstrofe a cualquier cadena que empiece con un carácter
     * que Excel interpretaría como fórmula. Preserva null y cadenas vacías.
     */
    public static function escape(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return $valor;
        }

        $primerCaracter = $valor[0];

        if (in_array($primerCaracter, self::CARACTERES_PELIGROSOS, true)) {
            return "'" . $valor;
        }

        return $valor;
    }
}
