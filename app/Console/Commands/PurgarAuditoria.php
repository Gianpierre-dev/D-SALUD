<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RegistroAuditoria;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Política de retención del log de auditoría.
 *
 * Elimina registros con más antigüedad que la indicada (por defecto 1 año).
 * Pensado para ejecutarse mensualmente vía el scheduler de Laravel.
 *
 * Antes de borrar en producción se recomienda exportar el rango purgado
 * a Wasabi/S3 (mediante el módulo de Reportes) para conservar el histórico
 * exigido por DIGEMID/SUNAT (5 años).
 */
class PurgarAuditoria extends Command
{
    protected $signature = 'auditoria:purgar {--dias=365 : Antigüedad mínima en días para purgar}';

    protected $description = 'Elimina registros de auditoría con más antigüedad que la indicada';

    public function handle(): int
    {
        $dias = (int) $this->option('dias');

        if ($dias < 30) {
            $this->error('El parámetro --dias debe ser mayor o igual a 30 (mínimo recomendado).');

            return self::FAILURE;
        }

        $corte = Carbon::now()->subDays($dias);
        $eliminados = RegistroAuditoria::query()
            ->where('created_at', '<', $corte)
            ->delete();

        $this->info("Purgados {$eliminados} registros de auditoría anteriores a {$corte->toDateString()}.");

        return self::SUCCESS;
    }
}
