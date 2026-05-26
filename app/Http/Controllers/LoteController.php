<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lote\StoreLoteRequest;
use App\Http\Requests\Lote\UpdateLoteRequest;
use App\Models\Lote;
use App\Services\LoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoteController extends Controller
{
    public function __construct(private readonly LoteService $service)
    {
    }

    public function index(Request $request): Response
    {
        $buscar = $request->string('buscar')->trim()->value() ?: null;

        return Inertia::render('Lotes/Index', [
            'lotes'      => $this->service->paginar($buscar),
            'productos'  => $this->service->productosActivos(),
            'proveedores' => $this->service->proveedoresActivos(),
            'filtros'    => ['buscar' => $buscar],
            'diasAlerta' => config('dsalud.inventario.dias_alerta_vencimiento'),
        ]);
    }

    public function store(StoreLoteRequest $request): RedirectResponse
    {
        $this->service->crear($request->validated());

        return back()->with('success', 'Lote creado correctamente.');
    }

    public function update(UpdateLoteRequest $request, Lote $lote): RedirectResponse
    {
        $this->service->actualizar($lote, $request->validated());

        return back()->with('success', 'Lote actualizado correctamente.');
    }

    public function destroy(Lote $lote): RedirectResponse
    {
        try {
            $this->service->eliminar($lote);

            return back()->with('success', 'Lote eliminado correctamente.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
