<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Http\Requests\Venta\AnularVentaRequest;
use App\Http\Requests\Venta\StoreVentaRequest;
use App\Models\Venta;
use App\Services\EmpresaService;
use App\Services\VentaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class VentaController extends Controller
{
    public function __construct(
        private readonly VentaService $service,
        private readonly EmpresaService $empresa,
    ) {
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
     *
     * Idempotencia: si el cliente envía un header Idempotency-Key (UUID emitido
     * al abrir el POS) reutilizamos la venta ya generada para esa key durante
     * los 60 s siguientes. Esto blinda contra dobles clicks, reintentos por
     * red inestable y reenvíos del navegador sin tocar la lógica del service.
     */
    public function store(StoreVentaRequest $request): RedirectResponse
    {
        $userId = $request->user()->id;
        $idempotencyKey = $this->resolverIdempotencyKey($request, $userId);

        if ($idempotencyKey !== null) {
            $ventaIdPrevia = Cache::get($idempotencyKey);
            if ($ventaIdPrevia !== null) {
                $venta = Venta::with('boleta')->find($ventaIdPrevia);
                if ($venta !== null) {
                    return redirect()
                        ->route('ventas.boleta', $venta)
                        ->with('success', 'Venta ya registrada. Boleta ' . $venta->boleta->numero_formateado . '.');
                }
            }
        }

        try {
            $venta = $this->service->registrar(
                $request->validated()['items'],
                $userId,
            );

            if ($idempotencyKey !== null) {
                Cache::put($idempotencyKey, $venta->id, now()->addSeconds(60));
            }

            return redirect()
                ->route('ventas.boleta', $venta)
                ->with('success', 'Venta registrada. Boleta ' . $venta->boleta->numero_formateado . '.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Normaliza la Idempotency-Key recibida del cliente. Devuelve la clave de
     * cache final namespaced por usuario para evitar colisiones entre cajas.
     */
    private function resolverIdempotencyKey(Request $request, int $userId): ?string
    {
        $raw = (string) $request->header('Idempotency-Key', '');
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        // Acotamos longitud y caracteres para evitar abuso del key como vector
        // de envenenamiento del store. UUIDv4 cabe en 36 chars; aceptamos hasta
        // 64 alfanuméricos por flexibilidad de generadores.
        if (! preg_match('/^[A-Za-z0-9\-_]{8,64}$/', $raw)) {
            return null;
        }

        return "venta.idempotency.{$userId}.{$raw}";
    }

    /**
     * Historial paginado de ventas con filtros.
     */
    public function index(Request $request): Response
    {
        $esAdmin = $request->user()->hasRole(Rol::ADMINISTRADOR->value);

        $filtros = [
            'fecha'       => $request->string('fecha')->trim()->value() ?: null,
            // El vendedor solo ve sus propias ventas; el administrador puede filtrar por cualquiera.
            'vendedor_id' => $esAdmin ? ($request->integer('vendedor_id') ?: null) : $request->user()->id,
            'estado'      => $request->string('estado')->trim()->value() ?: null,
        ];

        return Inertia::render('Ventas/Index', [
            'ventas'     => $this->service->paginarHistorial($filtros),
            'vendedores' => $esAdmin ? $this->service->vendedores() : [],
            'filtros'    => $filtros,
            'esAdmin'    => $esAdmin,
        ]);
    }

    /**
     * Vista de boleta imprimible.
     */
    public function boleta(Venta $venta): Response
    {
        $usuario = auth()->user();

        // Un vendedor solo puede ver las boletas de sus propias ventas.
        if (! $usuario->hasRole(Rol::ADMINISTRADOR->value) && $venta->user_id !== $usuario->id) {
            abort(403, 'No tienes permiso para ver esta boleta.');
        }

        $venta->load('detalles.producto', 'boleta', 'vendedor');

        return Inertia::render('Ventas/Boleta', [
            'venta'   => $venta,
            'empresa' => $this->empresa->obtener(),
        ]);
    }

    /**
     * Anula la venta y repone el stock de los lotes.
     */
    public function anular(AnularVentaRequest $request, Venta $venta): RedirectResponse
    {
        $usuario = $request->user();

        // Sin este chequeo cualquier Vendedor con permiso ventas.cancel podría
        // anular ventas de otros vendedores con sólo conocer el ID (IDOR).
        if (! $usuario->hasRole(Rol::ADMINISTRADOR->value) && $venta->user_id !== $usuario->id) {
            abort(403, 'No tienes permiso para anular esta venta.');
        }

        try {
            $this->service->anular($venta, $request->motivo, $usuario->id);

            return back()->with('success', 'Venta anulada y stock repuesto.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
