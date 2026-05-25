<?php

namespace App\Services;

use App\Models\Proveedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lógica de negocio del catálogo de proveedores.
 * Mantiene el controlador delgado y centraliza la auditoría.
 */
class ProveedorService
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    /**
     * Lista paginada de proveedores con búsqueda opcional por razón social o RUC.
     */
    public function paginar(?string $buscar): LengthAwarePaginator
    {
        return Proveedor::query()
            ->when($buscar, function ($query, $termino): void {
                $query->where('razon_social', 'like', "%{$termino}%")
                    ->orWhere('ruc', 'like', "%{$termino}%");
            })
            ->orderBy('razon_social')
            ->paginate(config('dsalud.paginacion.por_pagina'))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Proveedor
    {
        $proveedor = Proveedor::create($datos);
        $this->auditoria->registrar('proveedores', 'crear', "Proveedor #{$proveedor->id}: {$proveedor->razon_social}");

        return $proveedor;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Proveedor $proveedor, array $datos): Proveedor
    {
        $proveedor->update($datos);
        $this->auditoria->registrar('proveedores', 'actualizar', "Proveedor #{$proveedor->id}: {$proveedor->razon_social}");

        return $proveedor;
    }

    public function eliminar(Proveedor $proveedor): void
    {
        $this->auditoria->registrar('proveedores', 'eliminar', "Proveedor #{$proveedor->id}: {$proveedor->razon_social}");
        $proveedor->delete();
    }
}
