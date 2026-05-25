<?php

namespace App\Http\Controllers;

use App\Http\Requests\Proveedor\StoreProveedorRequest;
use App\Http\Requests\Proveedor\UpdateProveedorRequest;
use App\Models\Proveedor;
use App\Services\ProveedorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorController extends Controller
{
    public function __construct(private readonly ProveedorService $service)
    {
    }

    public function index(Request $request): Response
    {
        $buscar = $request->string('buscar')->trim()->value() ?: null;

        return Inertia::render('Proveedores/Index', [
            'proveedores' => $this->service->paginar($buscar),
            'filtros'     => ['buscar' => $buscar],
        ]);
    }

    public function store(StoreProveedorRequest $request): RedirectResponse
    {
        $this->service->crear($request->validated());

        return back()->with('success', 'Proveedor creado correctamente.');
    }

    public function update(UpdateProveedorRequest $request, Proveedor $proveedor): RedirectResponse
    {
        $this->service->actualizar($proveedor, $request->validated());

        return back()->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        $this->service->eliminar($proveedor);

        return back()->with('success', 'Proveedor eliminado correctamente.');
    }
}
