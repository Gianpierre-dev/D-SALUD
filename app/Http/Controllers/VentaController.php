<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Http\Requests\Venta\AnularVentaRequest;
use App\Http\Requests\Venta\StoreVentaRequest;
use App\Models\Venta;
use App\Services\ClienteService;
use App\Services\EmpresaService;
use App\Services\VentaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class VentaController extends Controller
{
    public function __construct(
        private readonly VentaService $service,
        private readonly EmpresaService $empresa,
        private readonly ClienteService $clientes,
    ) {
    }

    /**
     * Punto de venta (POS): formulario de nueva venta.
     */
    public function create(): Response
    {
        return Inertia::render('Ventas/Create', [
            'productos' => $this->service->productosDisponibles(),
            'clientes'  => $this->clientes->activos(),
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
            $validated = $request->validated();
            $clienteId = isset($validated['cliente_id']) ? (int) $validated['cliente_id'] : null;

            $venta = $this->service->registrar(
                $validated['items'],
                $userId,
                $clienteId,
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
            'cliente_id'  => $request->integer('cliente_id') ?: null,
            'estado'      => $request->string('estado')->trim()->value() ?: null,
        ];

        return Inertia::render('Ventas/Index', [
            'ventas'     => $this->service->paginarHistorial($filtros),
            'vendedores' => $esAdmin ? $this->service->vendedores() : [],
            // Lista compartida con el POS: cliente como atajo de filtro.
            'clientes'   => $this->clientes->activos(),
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

        $venta->load('detalles.producto', 'boleta', 'vendedor', 'cliente');

        return Inertia::render('Ventas/Boleta', [
            'venta'   => $venta,
            'empresa' => $this->empresa->obtener(),
        ]);
    }

    /**
     * Descarga la boleta en formato PDF generado server-side con DomPDF.
     *
     * Replica el guard de ownership de boleta(): un Vendedor solo puede
     * descargar el PDF de sus propias ventas. El PDF se arma a partir
     * del Blade resources/views/pdfs/boleta.blade.php; el logo se embebe
     * leyendo el archivo desde public/logo.png para que el PDF resultante
     * no dependa de la URL pública.
     */
    public function boletaPdf(Request $request, Venta $venta): HttpResponse
    {
        $usuario = $request->user();

        if (! $usuario->hasRole(Rol::ADMINISTRADOR->value) && $venta->user_id !== $usuario->id) {
            abort(403, 'No tienes permiso para descargar esta boleta.');
        }

        $venta->load('detalles.producto', 'boleta', 'vendedor', 'cliente');

        $logoPath = public_path('logo.png');
        $empresa  = $this->empresa->obtener();

        $pdf = Pdf::loadView('pdfs.boleta', [
            'venta'    => $venta,
            'empresa'  => $empresa,
            'logoPath' => $logoPath,
        ])->setPaper('a4', 'portrait');

        $nombre = sprintf(
            'boleta_%s.pdf',
            $venta->boleta?->numero_formateado
                ? str_replace(['/', ' '], '_', $venta->boleta->numero_formateado)
                : $venta->id,
        );

        return $pdf->download($nombre);
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
