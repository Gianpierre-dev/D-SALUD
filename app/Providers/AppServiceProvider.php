<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\AuditoriaAuthListener;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Política de contraseñas (NIST SP 800-63B): longitud mínima 10,
        // combinación de mayúsculas, minúsculas, números y símbolos.
        // En producción adicionalmente se rechazan contraseñas comprometidas.
        Password::defaults(function (): Password {
            $regla = Password::min(10)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();

            return $this->app->isProduction()
                ? $regla->uncompromised()
                : $regla;
        });

        // En producción, forzar HTTPS para todas las URL generadas por la app.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Auditoría de eventos de autenticación (login, logout, fallos, bloqueos).
        Event::listen(Login::class, [AuditoriaAuthListener::class, 'handleLogin']);
        Event::listen(Logout::class, [AuditoriaAuthListener::class, 'handleLogout']);
        Event::listen(Failed::class, [AuditoriaAuthListener::class, 'handleFailed']);
        Event::listen(Lockout::class, [AuditoriaAuthListener::class, 'handleLockout']);
    }
}
