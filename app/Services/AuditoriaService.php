<?php

namespace App\Services;

use App\Models\RegistroAuditoria;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Registra operaciones críticas del sistema en el log de auditoría.
 * Responsabilidad única: persistir un evento de auditoría con el contexto
 * del usuario y la petición actuales.
 */
class AuditoriaService
{
    /**
     * Registra un evento de auditoría.
     *
     * @param  string       $modulo   Módulo donde ocurre la acción (p. ej. "productos").
     * @param  string       $accion   Acción realizada (p. ej. "crear", "anular").
     * @param  string|null  $detalle  Información adicional del evento.
     */
    public function registrar(string $modulo, string $accion, ?string $detalle = null): RegistroAuditoria
    {
        return RegistroAuditoria::create([
            'user_id' => Auth::id(),
            'modulo' => $modulo,
            'accion' => $accion,
            'ip' => Request::ip(),
            'detalle' => $detalle,
        ]);
    }
}
