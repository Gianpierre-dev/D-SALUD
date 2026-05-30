<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Rol;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Lógica de negocio del módulo de roles.
 * Mantiene el controlador delgado y centraliza la auditoría.
 */
class RolService
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    /**
     * Lista paginada de roles con conteo de permisos, con búsqueda opcional por nombre.
     */
    public function paginar(?string $buscar): LengthAwarePaginator
    {
        return Role::query()
            ->withCount('permissions')
            ->with('permissions:id,name')
            ->when($buscar, fn ($query, $termino) => $query->where('name', 'like', "%{$termino}%"))
            ->orderBy('name')
            ->paginate(config('dsalud.paginacion.por_pagina'))
            ->withQueryString();
    }

    /**
     * Crea un rol con guard web y le asigna los permisos indicados.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Role
    {
        $rol = Role::create([
            'name'       => $datos['name'],
            'guard_name' => 'web',
        ]);

        if (!empty($datos['permissions'])) {
            $rol->syncPermissions($datos['permissions']);
        }

        $this->auditoria->registrar('roles', 'crear', "Rol #{$rol->id}: {$rol->name}");

        return $rol;
    }

    /**
     * Actualiza el nombre y los permisos de un rol.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Role $rol, array $datos): Role
    {
        // No permitir renombrar roles del sistema: el nombre se usa en
        // hasRole() a lo largo del código; renombrarlo rompería la autorización.
        if (in_array($rol->name, Rol::values(), true) && $rol->name !== $datos['name']) {
            throw new \RuntimeException(
                "El rol \"{$rol->name}\" es un rol del sistema y no puede renombrarse."
            );
        }

        $rol->update(['name' => $datos['name']]);
        $rol->syncPermissions($datos['permissions'] ?? []);

        $this->auditoria->registrar('roles', 'actualizar', "Rol #{$rol->id}: {$rol->name}");

        return $rol;
    }

    /**
     * Elimina un rol. Lanza excepción si es un rol protegido del sistema.
     *
     * @throws \RuntimeException
     */
    public function eliminar(Role $rol): void
    {
        if (in_array($rol->name, Rol::values(), true)) {
            throw new \RuntimeException("El rol \"{$rol->name}\" es un rol del sistema y no puede eliminarse.");
        }

        $this->auditoria->registrar('roles', 'eliminar', "Rol #{$rol->id}: {$rol->name}");
        $rol->delete();
    }

    /**
     * Devuelve todos los permisos agrupados por módulo (prefijo antes del punto).
     *
     * Ejemplo de salida:
     * [
     *   'categorias' => [['id'=>1,'name'=>'categorias.read'], ...],
     *   'productos'  => [...],
     * ]
     *
     * @return array<string, Collection<int, Permission>>
     */
    public function listarPermisos(): array
    {
        $permisos = Permission::orderBy('name')->get(['id', 'name']);

        return $permisos
            ->groupBy(fn (Permission $p) => explode('.', $p->name)[0])
            ->toArray();
    }
}
