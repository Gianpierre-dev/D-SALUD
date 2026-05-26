<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Producto\StoreProductoRequest;
use App\Http\Requests\Producto\UpdateProductoRequest;
use App\Models\Producto;
use App\Services\ProductoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductoController extends Controller
{
    public function __construct(private readonly ProductoService $service)
    {
    }

    public function index(Request $request): Response
    {
        $buscar = $request->string('buscar')->trim()->value() ?: null;

        return Inertia::render('Productos/Index', [
            'productos'  => $this->service->paginar($buscar),
            'categorias' => $this->service->categoriasActivas(),
            'filtros'    => ['buscar' => $buscar],
        ]);
    }

    public function store(StoreProductoRequest $request): RedirectResponse
    {
        $this->service->crear($request->validated());

        return back()->with('success', 'Producto creado correctamente.');
    }

    public function update(UpdateProductoRequest $request, Producto $producto): RedirectResponse
    {
        $this->service->actualizar($producto, $request->validated());

        return back()->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        $this->service->eliminar($producto);

        return back()->with('success', 'Producto eliminado correctamente.');
    }
}
