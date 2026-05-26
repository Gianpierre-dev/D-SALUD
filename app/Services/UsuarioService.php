<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
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
                fn ($query, $termino) => $query
                    ->where('name', 'like', "%{$termino}%")
                    ->orWhere('email', 'like', "%{$termino}%"),
            )
            ->orderBy('name')
            ->paginate(config('dsalud.paginacion.por_pagina'))
            ->withQueryString();
    }

    /**
     * Crea un usuario y le asigna el rol indicado.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): User
    {
        $user = User::create([
            'name'     => $datos['name'],
            'email'    => $datos['email'],
            'password' => Hash::make($datos['password']),
        ]);

        $user->syncRoles($datos['rol']);

        $this->auditoria->registrar('usuarios', 'crear', "Usuario #{$user->id}: {$user->name} ({$user->email})");

        return $user;
    }

    /**
     * Actualiza un usuario. Solo cambia el password si se envía uno nuevo.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(User $user, array $datos): User
    {
        $payload = [
            'name'  => $datos['name'],
            'email' => $datos['email'],
        ];

        if (!empty($datos['password'])) {
            $payload['password'] = Hash::make($datos['password']);
        }

        $user->update($payload);
        $user->syncRoles($datos['rol']);

        $this->auditoria->registrar('usuarios', 'actualizar', "Usuario #{$user->id}: {$user->name} ({$user->email})");

        return $user;
    }

    public function eliminar(User $user): void
    {
        $this->auditoria->registrar('usuarios', 'eliminar', "Usuario #{$user->id}: {$user->name} ({$user->email})");
        $user->delete();
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
}
