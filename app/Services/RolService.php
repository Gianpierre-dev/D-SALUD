<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Rol;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        return DB::transaction(function () use ($datos): Role {
            $rol = Role::create([
                'name'       => $datos['name'],
                'guard_name' => 'web',
            ]);

            if (! empty($datos['permissions'])) {
                $rol->syncPermissions($datos['permissions']);
            }

            $this->auditoria->registrar('roles', 'crear', "Rol #{$rol->id}: {$rol->name}");

            return $rol;
        });
    }

    /**
     * Actualiza el nombre y los permisos de un rol.
     *
     * Los roles del sistema (Administrador, Vendedor) son inmutables tanto en
     * nombre como en permisos: el código los referencia por name vía hasRole()
     * y altera su perímetro de privilegio sería privilege escalation.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Role $rol, array $datos): Role
    {
        if ($this->esRolDelSistema($rol)) {
            throw new \RuntimeException(
                "El rol \"{$rol->name}\" es un rol del sistema y no puede modificarse."
            );
        }

        // user_ids ANTES de la transacción para invalidar payload de cada usuario
        // afectado: el cache user.{id}.payload trae roles+permissions resueltos y
        // sin esta invalidación los usuarios mantendrían permisos viejos hasta el TTL.
        $userIds = $rol->users()->pluck('id');

        $rol = DB::transaction(function () use ($rol, $datos): Role {
            $rol->update(['name' => $datos['name']]);
            $rol->syncPermissions($datos['permissions'] ?? []);

            $this->auditoria->registrar('roles', 'actualizar', "Rol #{$rol->id}: {$rol->name}");

            return $rol;
        });

        $this->invalidarCachePayloads($userIds->all());

        return $rol;
    }

    /**
     * Elimina un rol. Lanza excepción si es un rol protegido del sistema.
     *
     * @throws \RuntimeException
     */
    public function eliminar(Role $rol): void
    {
        if ($this->esRolDelSistema($rol)) {
            throw new \RuntimeException("El rol \"{$rol->name}\" es un rol del sistema y no puede eliminarse.");
        }

        $userIds = $rol->users()->pluck('id');

        DB::transaction(function () use ($rol): void {
            $this->auditoria->registrar('roles', 'eliminar', "Rol #{$rol->id}: {$rol->name}");
            $rol->delete();
        });

        $this->invalidarCachePayloads($userIds->all());
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

    private function esRolDelSistema(Role $rol): bool
    {
        return in_array($rol->name, Rol::values(), true);
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function invalidarCachePayloads(array $userIds): void
    {
        foreach ($userIds as $id) {
            Cache::forget("user.{$id}.payload");
            Cache::forget("user.{$id}.roles");
            Cache::forget("user.{$id}.permissions");
        }
    }
}
