<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Empresa;

/**
 * Lógica de negocio de la configuración de empresa (singleton, id=1).
 * El controlador se mantiene delgado; la auditoría se centraliza aquí.
 */
class EmpresaService
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    /**
     * Obtiene la única fila de empresa.
     * Si no existe lanza ModelNotFoundException (falla visible en log).
     */
    public function obtener(): Empresa
    {
        return Empresa::firstOrFail();
    }

    /**
     * Actualiza los datos de la empresa y registra la auditoría.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(array $datos): Empresa
    {
        $empresa = $this->obtener();
        $empresa->update($datos);

        $this->auditoria->registrar('empresa', 'actualizar', "Empresa #{$empresa->id}: {$empresa->razon_social}");

        return $empresa;
    }
}
