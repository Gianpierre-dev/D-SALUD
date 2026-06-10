<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Railway (y cualquier PaaS) sirve la app detrás de un proxy que
        // termina el TLS. Confiar en los headers X-Forwarded-* permite que
        // Laravel detecte HTTPS y genere URLs/redirecciones seguras.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Alias de middleware de control de acceso (spatie/laravel-permission).
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Renderiza páginas de error amigables (Inertia) en producción para
        // 403/404/419/500/503. En local se mantiene el debugger de Laravel.
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (! app()->environment('production')) {
                return null;
            }

            if ($request->expectsJson()) {
                return null;
            }

            // No interceptar excepciones con manejo nativo propio de Laravel:
            //  - AuthenticationException -> redirige a login (302)
            //  - ValidationException     -> vuelve con errores (422)
            // Si se interceptaran, ambas terminarían como un 500 falso porque
            // no exponen getStatusCode().
            if ($e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }

            // Las HttpException ya traen su código (404, 403, 503...). Cualquier
            // otra excepción no controlada es un 500 real.
            $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            if (! in_array($status, [403, 404, 419, 500, 503], true)) {
                return null;
            }

            return \Inertia\Inertia::render('Errors/Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
