<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Http\Requests\Cliente\UpdateClienteRequest;
use App\Models\Cliente;
use App\Services\ClienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function __construct(private readonly ClienteService $service)
    {
    }

    public function index(Request $request): Response
    {
        $buscar = $request->string('buscar')->trim()->value() ?: null;

        return Inertia::render('Clientes/Index', [
            'clientes' => $this->service->paginar($buscar),
            'filtros'  => ['buscar' => $buscar],
        ]);
    }

    public function store(StoreClienteRequest $request): RedirectResponse
    {
        $this->service->crear($request->validated());

        return back()->with('success', 'Cliente creado correctamente.');
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $this->service->actualizar($cliente, $request->validated());

        return back()->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        try {
            $this->service->eliminar($cliente);
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->with(
                'error',
                'No se puede eliminar el cliente porque tiene ventas asociadas.'
            );
        }

        return back()->with('success', 'Cliente eliminado correctamente.');
    }
}
