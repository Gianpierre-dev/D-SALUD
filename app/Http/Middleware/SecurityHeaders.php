<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
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
 *  - Inyección de scripts/recursos en producción (Content-Security-Policy).
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

            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        return $response;
    }

    /**
     * Construye la política CSP. Los scripts se permiten solo desde el mismo
     * origen y con el nonce por-request (sin 'unsafe-inline' ni 'unsafe-eval').
     * style-src incluye 'unsafe-inline' por los estilos en línea de React/Recharts.
     */
    private function contentSecurityPolicy(): string
    {
        $nonce = Vite::cspNonce();
        $scriptSrc = "script-src 'self'" . ($nonce !== null ? " 'nonce-{$nonce}'" : '');

        return implode('; ', [
            "default-src 'self'",
            $scriptSrc,
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net",
            "img-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
