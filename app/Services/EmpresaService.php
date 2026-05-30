<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Support\Facades\Cache;

/**
 * Lógica de negocio de la configuración de empresa (singleton, id=1).
 * El controlador se mantiene delgado; la auditoría se centraliza aquí.
 */
class EmpresaService
{
    private const CACHE_KEY = 'empresa.config';

    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    /**
     * Obtiene la única fila de empresa, cacheada hasta que se actualice.
     * Si no existe lanza ModelNotFoundException (falla visible en log).
     */
    public function obtener(): Empresa
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): Empresa => Empresa::firstOrFail(),
        );
    }

    /**
     * Actualiza los datos de la empresa, invalida la cache y registra auditoría.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(array $datos): Empresa
    {
        $empresa = Empresa::firstOrFail();
        $empresa->update($datos);

        Cache::forget(self::CACHE_KEY);

        $this->auditoria->registrar('empresa', 'actualizar', "Empresa #{$empresa->id}: {$empresa->razon_social}");

        return $empresa;
    }
}
