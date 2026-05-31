<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoCompra;
use App\Http\Requests\Compra\AnularCompraRequest;
use App\Http\Requests\Compra\StoreCompraRequest;
use App\Http\Requests\Compra\UpdateCompraRequest;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\CompraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompraController extends Controller
{
    public function __construct(private readonly CompraService $service)
    {
    }

    public function index(Request $request): Response
    {
        $filtros = [
            'estado'       => $request->string('estado')->trim()->value() ?: null,
            'proveedor_id' => $request->integer('proveedor_id') ?: null,
            'fecha'        => $request->string('fecha')->trim()->value() ?: null,
        ];

        return Inertia::render('Compras/Index', [
            'compras'    => $this->service->paginar($filtros),
            'filtros'    => $filtros,
            'proveedores' => Proveedor::query()
                ->where('activo', true)
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'ruc']),
            'estados'    => array_map(
                static fn (EstadoCompra $e) => ['value' => $e->value, 'label' => $e->etiqueta()],
                EstadoCompra::cases(),
            ),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Compras/Create', [
            'proveedores' => $this->proveedoresActivos(),
            'productos'   => $this->productosActivos(),
        ]);
    }

    public function store(StoreCompraRequest $request): RedirectResponse
    {
        $compra = $this->service->crear($request->validated(), $request->user()->id);

        return redirect()
            ->route('compras.show', $compra)
            ->with('success', "Compra {$compra->numero_formateado} registrada en estado PENDIENTE.");
    }

    public function show(Compra $compra): Response
    {
        $compra->load([
            'detalles.producto:id,codigo,nombre',
            'proveedor:id,razon_social,ruc,telefono',
            'registradaPor:id,name',
            'recibidaPor:id,name',
            'anuladaPor:id,name',
        ]);

        return Inertia::render('Compras/Show', [
            'compra' => $compra,
        ]);
    }

    public function edit(Compra $compra): Response
    {
        if ($compra->estado !== EstadoCompra::PENDIENTE) {
            abort(403, 'Solo las compras PENDIENTES pueden editarse.');
        }

        $compra->load('detalles.producto:id,codigo,nombre');

        return Inertia::render('Compras/Edit', [
            'compra'      => $compra,
            'proveedores' => $this->proveedoresActivos(),
            'productos'   => $this->productosActivos(),
        ]);
    }

    public function update(UpdateCompraRequest $request, Compra $compra): RedirectResponse
    {
        try {
            $this->service->actualizar($compra, $request->validated());

            return redirect()
                ->route('compras.show', $compra)
                ->with('success', 'Compra actualizada correctamente.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Recibe la mercadería: PENDIENTE → RECIBIDA.
     */
    public function recibir(Request $request, Compra $compra): RedirectResponse
    {
        try {
            $this->service->recibir($compra, $request->user()->id);

            return back()->with(
                'success',
                "Compra {$compra->numero_formateado} recibida. Lotes generados y kardex actualizado.",
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(AnularCompraRequest $request, Compra $compra): RedirectResponse
    {
        try {
            $this->service->anular($compra, $request->validated()['motivo'], $request->user()->id);

            return redirect()
                ->route('compras.index')
                ->with('success', "Compra {$compra->numero_formateado} anulada.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function proveedoresActivos()
    {
        return Proveedor::query()
            ->where('activo', true)
            ->orderBy('razon_social')
            ->get(['id', 'razon_social', 'ruc']);
    }

    private function productosActivos()
    {
        return Producto::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);
    }
}
