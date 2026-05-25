<?php

namespace App\Http\Controllers;

use App\Http\Requests\Venta\AnularVentaRequest;
use App\Http\Requests\Venta\StoreVentaRequest;
use App\Models\Empresa;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VentaController extends Controller
{
    public function __construct(private readonly VentaService $service)
    {
    }

    /**
     * Punto de venta (POS): formulario de nueva venta.
     */
    public function create(): Response
    {
        return Inertia::render('Ventas/Create', [
            'productos' => $this->service->productosDisponibles(),
        ]);
    }

    /**
     * Registra la venta y redirige a la boleta generada.
     */
    public function store(StoreVentaRequest $request): RedirectResponse
    {
        try {
            $venta = $this->service->registrar(
                $request->validated()['items'],
                auth()->id()
            );

            return redirect()
                ->route('ventas.boleta', $venta)
                ->with('success', 'Venta registrada. Boleta ' . $venta->boleta->numero_formateado . '.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Historial paginado de ventas con filtros.
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'fecha'       => $request->string('fecha')->trim()->value() ?: null,
            'vendedor_id' => $request->integer('vendedor_id') ?: null,
            'estado'      => $request->string('estado')->trim()->value() ?: null,
        ];

        return Inertia::render('Ventas/Index', [
            'ventas'    => $this->service->paginarHistorial($filtros),
            'vendedores' => $this->service->vendedores(),
            'filtros'   => $filtros,
        ]);
    }

    /**
     * Vista de boleta imprimible.
     */
    public function boleta(Venta $venta): Response
    {
        $venta->load('detalles.producto', 'boleta', 'vendedor');

        return Inertia::render('Ventas/Boleta', [
            'venta'   => $venta,
            'empresa' => Empresa::first(),
        ]);
    }

    /**
     * Anula la venta y repone el stock de los lotes.
     */
    public function anular(AnularVentaRequest $request, Venta $venta): RedirectResponse
    {
        try {
            $this->service->anular($venta, $request->motivo, auth()->id());

            return back()->with('success', 'Venta anulada y stock repuesto.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
