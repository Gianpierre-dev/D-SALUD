<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Categoria\StoreCategoriaRequest;
use App\Http\Requests\Categoria\UpdateCategoriaRequest;
use App\Models\Categoria;
use App\Services\CategoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaController extends Controller
{
    public function __construct(private readonly CategoriaService $service)
    {
    }

    public function index(Request $request): Response
    {
        $buscar = $request->string('buscar')->trim()->value() ?: null;

        return Inertia::render('Categorias/Index', [
            'categorias' => $this->service->paginar($buscar),
            'filtros' => ['buscar' => $buscar],
        ]);
    }

    public function store(StoreCategoriaRequest $request): RedirectResponse
    {
        $this->service->crear($request->validated());

        return back()->with('success', 'Categoría creada correctamente.');
    }

    public function update(UpdateCategoriaRequest $request, Categoria $categoria): RedirectResponse
    {
        $this->service->actualizar($categoria, $request->validated());

        return back()->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(Categoria $categoria): RedirectResponse
    {
        $this->service->eliminar($categoria);

        return back()->with('success', 'Categoría eliminada correctamente.');
    }
}
