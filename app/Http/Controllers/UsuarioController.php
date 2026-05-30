<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Usuario\StoreUsuarioRequest;
use App\Http\Requests\Usuario\UpdateUsuarioRequest;
use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function __construct(private readonly UsuarioService $service)
    {
    }

    public function index(Request $request): Response
    {
        $buscar = $request->string('buscar')->trim()->value() ?: null;

        return Inertia::render('Usuarios/Index', [
            'usuarios' => $this->service->paginar($buscar),
            'roles'    => $this->service->listarRoles(),
            'filtros'  => ['buscar' => $buscar],
        ]);
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $this->service->crear($request->validated());

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function update(UpdateUsuarioRequest $request, User $user): RedirectResponse
    {
        try {
            $this->service->actualizar($user, $request->validated(), $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        try {
            $this->service->eliminar($user, $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Usuario eliminado correctamente.');
    }
}
