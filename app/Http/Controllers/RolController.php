<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Rol\StoreRolRequest;
use App\Http\Requests\Rol\UpdateRolRequest;
use App\Services\RolService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RolController extends Controller
{
    public function __construct(private readonly RolService $service)
    {
    }

    public function index(Request $request): Response
    {
        $buscar = $request->string('buscar')->trim()->value() ?: null;

        return Inertia::render('Roles/Index', [
            'roles'     => $this->service->paginar($buscar),
            'permisos'  => $this->service->listarPermisos(),
            'filtros'   => ['buscar' => $buscar],
        ]);
    }

    public function store(StoreRolRequest $request): RedirectResponse
    {
        $this->service->crear($request->validated());

        return back()->with('success', 'Rol creado correctamente.');
    }

    public function update(UpdateRolRequest $request, Role $role): RedirectResponse
    {
        $this->service->actualizar($role, $request->validated());

        return back()->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        try {
            $this->service->eliminar($role);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rol eliminado correctamente.');
    }
}
