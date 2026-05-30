<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica cabeceras de seguridad HTTP a todas las respuestas.
 *
 * Cubre:
 *  - Clickjacking (X-Frame-Options).
 *  - MIME sniffing (X-Content-Type-Options).
 *  - Fugas de referer (Referrer-Policy).
 *  - Acceso a APIs sensibles del navegador (Permissions-Policy).
 *  - Downgrade de HTTPS en producción (Strict-Transport-Security).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()'
        );

        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=63072000; includeSubDomains; preload'
            );
        }

        return $response;
    }
}
