<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| Requieren un servicio `php artisan schedule:work` corriendo en paralelo
| al servicio web (configurar en Railway como servicio adicional).
*/

// Purga sesiones expiradas semanalmente.
Schedule::command('session:prune-expired')->weekly();

// Purga jobs fallidos con más de una semana mensualmente.
Schedule::command('queue:prune-failed --hours=168')->monthly();

// Política de retención de auditoría: archiva registros con más de 1 año.
Schedule::command('auditoria:purgar --dias=365')->monthly();
