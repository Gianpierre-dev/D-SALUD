<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\RegistroAuditoria;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Request as RequestFacade;

/**
 * Registra los eventos de autenticación en la tabla de auditoría.
 *
 * Esta visibilidad es exigida por DIGEMID/SUNAT en sistemas farmacéuticos:
 * todo acceso al sistema debe quedar trazado, incluyendo intentos fallidos.
 *
 * Se evita inyectar AuditoriaService para no acoplar el listener a la
 * sesión activa: en eventos de Failed/Lockout, Auth::id() es null y eso
 * es lo que queremos registrar.
 */
class AuditoriaAuthListener
{
    public function handleLogin(Login $event): void
    {
        RegistroAuditoria::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'modulo' => 'autenticacion',
            'accion' => 'login',
            'ip' => RequestFacade::ip(),
            'detalle' => "Inicio de sesión correcto ({$event->guard}).",
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        RegistroAuditoria::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'modulo' => 'autenticacion',
            'accion' => 'logout',
            'ip' => RequestFacade::ip(),
            'detalle' => "Cierre de sesión ({$event->guard}).",
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? 'desconocido';

        RegistroAuditoria::create([
            'user_id' => $event->user?->getAuthIdentifier(),
            'modulo' => 'autenticacion',
            'accion' => 'login_fallido',
            'ip' => RequestFacade::ip(),
            'detalle' => "Intento de inicio de sesión fallido para: {$email}",
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        $email = (string) ($event->request->input('email') ?? 'desconocido');

        RegistroAuditoria::create([
            'user_id' => null,
            'modulo' => 'autenticacion',
            'accion' => 'bloqueado',
            'ip' => RequestFacade::ip(),
            'detalle' => "Demasiados intentos fallidos para: {$email}",
        ]);
    }
}
