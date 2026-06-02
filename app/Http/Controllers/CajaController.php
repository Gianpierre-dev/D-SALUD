<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoCaja;
use App\Enums\MedioPago;
use App\Enums\Rol;
use App\Enums\TipoMovimientoCaja;
use App\Http\Requests\Caja\AbrirCajaRequest;
use App\Http\Requests\Caja\CerrarCajaRequest;
use App\Http\Requests\Caja\MovimientoCajaRequest;
use App\Models\Caja;
use App\Services\CajaService;
use App\Services\EmpresaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CajaController extends Controller
{
    public function __construct(
        private readonly CajaService $service,
        private readonly EmpresaService $empresa,
    ) {
    }

    public function index(Request $request): Response
    {
        $esAdmin = $request->user()->hasRole(Rol::ADMINISTRADOR->value);

        $filtros = [
            // El vendedor solo ve sus propias cajas; el admin puede filtrar por cualquiera.
            'user_id' => $esAdmin ? ($request->integer('user_id') ?: null) : $request->user()->id,
            'estado'  => $request->string('estado')->trim()->value() ?: null,
        ];

        return Inertia::render('Cajas/Index', [
            'cajas'      => $this->service->paginar($filtros),
            'filtros'    => $filtros,
            'esAdmin'    => $esAdmin,
            'miCajaAbierta' => $this->service->cajaAbiertaDe($request->user()->id),
        ]);
    }

    public function show(Request $request, Caja $caja): Response
    {
        $this->autorizar($request, $caja);

        $caja->load([
            'cajero:id,name',
            'cerradaPor:id,name',
            'movimientos.usuario:id,name',
        ]);

        return Inertia::render('Cajas/Show', [
            'caja'             => $caja,
            'desglosePorMedio' => $this->desgloseConEtiquetas($caja),
            'tiposMovimiento'  => array_map(
                static fn (TipoMovimientoCaja $t) => ['value' => $t->value, 'label' => $t->etiqueta()],
                TipoMovimientoCaja::cases(),
            ),
        ]);
    }

    /**
     * Abre una nueva caja para el usuario autenticado.
     */
    public function store(AbrirCajaRequest $request): RedirectResponse
    {
        try {
            $caja = $this->service->abrir(
                $request->user()->id,
                (float) $request->validated()['monto_apertura'],
                $request->validated()['observaciones'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('cajas.show', $caja)
            ->with('success', 'Caja abierta. Ya puedes registrar ventas.');
    }

    /**
     * Registra un movimiento de efectivo (ingreso/egreso) en la caja abierta.
     */
    public function movimiento(MovimientoCajaRequest $request, Caja $caja): RedirectResponse
    {
        $this->autorizar($request, $caja);

        try {
            $datos = $request->validated();
            $this->service->registrarMovimiento(
                $caja,
                TipoMovimientoCaja::from($datos['tipo']),
                (float) $datos['monto'],
                $datos['concepto'],
                $request->user()->id,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Movimiento de caja registrado.');
    }

    /**
     * Cierra la caja y calcula el cuadre.
     */
    public function close(CerrarCajaRequest $request, Caja $caja): RedirectResponse
    {
        $this->autorizar($request, $caja);

        try {
            $this->service->cerrar(
                $caja,
                (float) $request->validated()['monto_cierre'],
                $request->user()->id,
                $request->validated()['observaciones'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('cajas.show', $caja)
            ->with('success', 'Caja cerrada. Reporte Z disponible.');
    }

    /**
     * Descarga el reporte Z (PDF de cuadre) de una caja CERRADA.
     */
    public function reporteZ(Request $request, Caja $caja): HttpResponse
    {
        $this->autorizar($request, $caja);

        if ($caja->estado !== EstadoCaja::CERRADA) {
            abort(409, 'La caja debe estar cerrada para generar el reporte Z.');
        }

        $caja->load(['cajero:id,name', 'cerradaPor:id,name', 'movimientos.usuario:id,name']);

        $pdf = Pdf::loadView('pdfs.reporte_z', [
            'caja'             => $caja,
            'empresa'          => $this->empresa->obtener(),
            'logoPath'         => public_path('logo.png'),
            'desglosePorMedio' => $this->desgloseConEtiquetas($caja),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("reporte_z_caja_{$caja->id}.pdf");
    }

    /**
     * Desglose por medio con etiquetas legibles para la UI/PDF.
     *
     * @return array<int, array{medio: string, label: string, total: float}>
     */
    private function desgloseConEtiquetas(Caja $caja): array
    {
        $desglose = $this->service->desglosePorMedio($caja);

        return array_map(
            static fn (string $medio, float $total) => [
                'medio' => $medio,
                'label' => MedioPago::from($medio)->etiqueta(),
                'total' => $total,
            ],
            array_keys($desglose),
            array_values($desglose),
        );
    }

    /**
     * El vendedor solo puede ver/cerrar sus propias cajas; el admin todas.
     */
    private function autorizar(Request $request, Caja $caja): void
    {
        $user = $request->user();
        if (! $user->hasRole(Rol::ADMINISTRADOR->value) && $caja->user_id !== $user->id) {
            abort(403, 'No tienes permiso para acceder a esta caja.');
        }
    }
}
