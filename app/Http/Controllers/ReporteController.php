<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\AuditoriaExport;
use App\Exports\LotesStockBajoExport;
use App\Exports\ProductosMasVendidosExport;
use App\Exports\ProductosPorVencerExport;
use App\Exports\VentasPorPeriodoExport;
use App\Services\ReporteService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReporteController extends Controller
{
    public function __construct(private readonly ReporteService $service)
    {
    }

    /**
     * Página principal del módulo de reportes.
     */
    public function index(): Response
    {
        return Inertia::render('Reportes/Index');
    }

    /**
     * Descarga: ventas completadas en un rango de fechas.
     */
    public function ventasPorPeriodo(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        $inicio = Carbon::parse($validated['fecha_inicio']);
        $fin    = Carbon::parse($validated['fecha_fin']);

        $ventas = $this->service->ventasPorPeriodo($inicio, $fin);

        $nombre = sprintf(
            'ventas_%s_%s.xlsx',
            $inicio->format('Ymd'),
            $fin->format('Ymd'),
        );

        return Excel::download(new VentasPorPeriodoExport($ventas), $nombre);
    }

    /**
     * Descarga: productos más vendidos en un rango de fechas.
     */
    public function productosMasVendidos(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        $inicio   = Carbon::parse($validated['fecha_inicio']);
        $fin      = Carbon::parse($validated['fecha_fin']);
        $productos = $this->service->productosMasVendidos($inicio, $fin);

        $nombre = sprintf(
            'productos_mas_vendidos_%s_%s.xlsx',
            $inicio->format('Ymd'),
            $fin->format('Ymd'),
        );

        return Excel::download(new ProductosMasVendidosExport($productos), $nombre);
    }

    /**
     * Descarga: lotes próximos a vencer según configuración del sistema.
     */
    public function productosPorVencer(): BinaryFileResponse
    {
        $lotes = $this->service->productosPorVencer();

        return Excel::download(
            new ProductosPorVencerExport($lotes),
            'productos_por_vencer_' . now()->format('Ymd') . '.xlsx',
        );
    }

    /**
     * Descarga: productos activos con stock total igual o por debajo del mínimo.
     */
    public function lotesStockBajo(): BinaryFileResponse
    {
        $productos = $this->service->lotesStockBajo();

        return Excel::download(
            new LotesStockBajoExport($productos),
            'stock_bajo_' . now()->format('Ymd') . '.xlsx',
        );
    }

    /**
     * Descarga: registros de auditoría, opcionalmente filtrados por fechas.
     */
    public function auditoria(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin'    => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        $inicio = isset($validated['fecha_inicio'])
            ? Carbon::parse($validated['fecha_inicio'])
            : null;

        $fin = isset($validated['fecha_fin'])
            ? Carbon::parse($validated['fecha_fin'])
            : null;

        $registros = $this->service->auditoria($inicio, $fin);

        return Excel::download(
            new AuditoriaExport($registros),
            'auditoria_' . now()->format('Ymd') . '.xlsx',
        );
    }
}
