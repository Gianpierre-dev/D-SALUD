<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoCaja;
use App\Enums\Rol;
use App\Http\Requests\Caja\AbrirCajaRequest;
use App\Http\Requests\Caja\CerrarCajaRequest;
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

        $caja->load(['cajero:id,name', 'cerradaPor:id,name']);

        return Inertia::render('Cajas/Show', [
            'caja' => $caja,
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

        $caja->load(['cajero:id,name', 'cerradaPor:id,name']);

        $pdf = Pdf::loadView('pdfs.reporte_z', [
            'caja'     => $caja,
            'empresa'  => $this->empresa->obtener(),
            'logoPath' => public_path('logo.png'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("reporte_z_caja_{$caja->id}.pdf");
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
