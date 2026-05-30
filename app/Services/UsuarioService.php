<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Lógica de negocio del módulo de usuarios.
 * Mantiene el controlador delgado y centraliza la auditoría.
 */
class UsuarioService
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    /**
     * Lista paginada de usuarios con sus roles, con búsqueda opcional por nombre o email.
     */
    public function paginar(?string $buscar): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when(
                $buscar,
                fn ($query, $termino) => $query->where(function ($q) use ($termino): void {
                    $q->where('name', 'like', "%{$termino}%")
                        ->orWhere('email', 'like', "%{$termino}%");
                }),
            )
            ->orderBy('name')
            ->paginate(config('dsalud.paginacion.por_pagina'))
            ->withQueryString();
    }

    /**
     * Crea un usuario y le asigna el rol indicado. Operación atómica.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): User
    {
        return DB::transaction(function () use ($datos): User {
            $user = User::create([
                'name'     => $datos['name'],
                'email'    => $datos['email'],
                'password' => Hash::make($datos['password']),
            ]);

            $user->syncRoles($datos['rol']);

            $this->auditoria->registrar('usuarios', 'crear', "Usuario #{$user->id}: {$user->name} ({$user->email})");

            return $user;
        });
    }

    /**
     * Actualiza un usuario. Solo cambia el password si se envía uno nuevo.
     *
     * Restricciones de seguridad:
     *  - El usuario no puede cambiar su propio rol (evita democión accidental).
     *  - No se puede dejar el sistema sin Administradores.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(User $user, array $datos, ?User $actor = null): User
    {
        $this->validarCambioDeRol($user, $datos['rol'], $actor);

        return DB::transaction(function () use ($user, $datos): User {
            $payload = [
                'name'  => $datos['name'],
                'email' => $datos['email'],
            ];

            if (! empty($datos['password'])) {
                $payload['password'] = Hash::make($datos['password']);
            }

            $user->update($payload);
            $user->syncRoles($datos['rol']);

            $this->auditoria->registrar('usuarios', 'actualizar', "Usuario #{$user->id}: {$user->name} ({$user->email})");

            $this->olvidarCachePermisos($user->id);

            return $user;
        });
    }

    /**
     * Elimina un usuario.
     *
     * Restricciones:
     *  - El usuario no puede eliminarse a sí mismo.
     *  - No se puede eliminar al único Administrador del sistema.
     */
    public function eliminar(User $user, ?User $actor = null): void
    {
        if ($actor !== null && $user->id === $actor->id) {
            throw new RuntimeException('No puedes eliminar tu propia cuenta.');
        }

        if ($this->esUltimoAdministrador($user)) {
            throw new RuntimeException('No se puede eliminar al único Administrador del sistema.');
        }

        $this->auditoria->registrar('usuarios', 'eliminar', "Usuario #{$user->id}: {$user->name} ({$user->email})");
        $this->olvidarCachePermisos($user->id);
        $user->delete();
    }

    /**
     * Invalida la cache de roles/permisos del usuario en HandleInertiaRequests.
     */
    private function olvidarCachePermisos(int $userId): void
    {
        Cache::forget("user.{$userId}.roles");
        Cache::forget("user.{$userId}.permissions");
    }

    /**
     * Devuelve todos los roles disponibles para poblar el select del formulario.
     *
     * @return Collection<int, Role>
     */
    public function listarRoles(): Collection
    {
        return Role::orderBy('name')->get(['id', 'name']);
    }

    private function validarCambioDeRol(User $user, string $nuevoRol, ?User $actor): void
    {
        $rolActual = $user->getRoleNames()->first();

        // El usuario no puede modificar su propio rol.
        if ($actor !== null && $user->id === $actor->id && $rolActual !== null && $rolActual !== $nuevoRol) {
            throw new RuntimeException('No puedes modificar tu propio rol.');
        }

        // Si el usuario era Administrador y se le quita el rol, verificar que no sea el último.
        if ($rolActual === Rol::ADMINISTRADOR->value
            && $nuevoRol !== Rol::ADMINISTRADOR->value
            && $this->esUltimoAdministrador($user)) {
            throw new RuntimeException('No se puede degradar al único Administrador del sistema.');
        }
    }

    private function esUltimoAdministrador(User $user): bool
    {
        if (! $user->hasRole(Rol::ADMINISTRADOR->value)) {
            return false;
        }

        return User::role(Rol::ADMINISTRADOR->value)->count() <= 1;
    }
}
