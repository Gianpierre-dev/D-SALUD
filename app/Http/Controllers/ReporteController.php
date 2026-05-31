<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\AuditoriaExport;
use App\Exports\KardexExport;
use App\Exports\LotesStockBajoExport;
use App\Exports\ProductosMasVendidosExport;
use App\Exports\ProductosPorVencerExport;
use App\Exports\VentasPorPeriodoExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReporteController extends Controller
{
    /**
     * Página principal del módulo de reportes.
     */
    public function index(): Response
    {
        // Productos activos solo se necesitan para el selector del Kardex.
        // payload mínimo (id+codigo+nombre) — sin stock, sin categoría.
        $productos = \App\Models\Producto::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        return Inertia::render('Reportes/Index', [
            'productos' => $productos,
        ]);
    }

    /**
     * Descarga: ventas completadas en un rango de fechas.
     */
    public function ventasPorPeriodo(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin'    => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ]);

        $inicio = Carbon::parse($validated['fecha_inicio']);
        $fin    = Carbon::parse($validated['fecha_fin']);

        $this->validarRangoMaximo($inicio, $fin);

        $nombre = sprintf('ventas_%s_%s.xlsx', $inicio->format('Ymd'), $fin->format('Ymd'));

        return Excel::download(new VentasPorPeriodoExport($inicio, $fin), $nombre);
    }

    /**
     * Descarga: productos más vendidos en un rango de fechas.
     */
    public function productosMasVendidos(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin'    => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ]);

        $inicio = Carbon::parse($validated['fecha_inicio']);
        $fin    = Carbon::parse($validated['fecha_fin']);

        $this->validarRangoMaximo($inicio, $fin);

        $nombre = sprintf(
            'productos_mas_vendidos_%s_%s.xlsx',
            $inicio->format('Ymd'),
            $fin->format('Ymd'),
        );

        return Excel::download(new ProductosMasVendidosExport($inicio, $fin), $nombre);
    }

    /**
     * Descarga: lotes próximos a vencer según configuración del sistema.
     */
    public function productosPorVencer(): BinaryFileResponse
    {
        $diasAlerta = (int) config('dsalud.inventario.dias_alerta_vencimiento', 30);
        $limite     = now()->addDays($diasAlerta)->endOfDay();

        return Excel::download(
            new ProductosPorVencerExport($limite),
            'productos_por_vencer_' . now()->format('Ymd') . '.xlsx',
        );
    }

    /**
     * Descarga: productos activos con stock total igual o por debajo del mínimo.
     */
    public function lotesStockBajo(): BinaryFileResponse
    {
        return Excel::download(
            new LotesStockBajoExport(),
            'stock_bajo_' . now()->format('Ymd') . '.xlsx',
        );
    }

    /**
     * Descarga: Kardex por producto en un rango de fechas.
     */
    public function kardex(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'producto_id'  => ['required', 'integer', 'exists:productos,id'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin'    => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ]);

        $inicio = Carbon::parse($validated['fecha_inicio']);
        $fin    = Carbon::parse($validated['fecha_fin']);

        $this->validarRangoMaximo($inicio, $fin);

        $nombre = sprintf(
            'kardex_producto_%d_%s_%s.xlsx',
            $validated['producto_id'],
            $inicio->format('Ymd'),
            $fin->format('Ymd'),
        );

        return Excel::download(
            new KardexExport((int) $validated['producto_id'], $inicio, $fin),
            $nombre,
        );
    }

    /**
     * Descarga: registros de auditoría, opcionalmente filtrados por fechas.
     *
     * Si llega un solo extremo, se completa con el otro para evitar que el
     * export itere años enteros y agote memoria. El throttle:10,1 de la ruta
     * es complementario, no suficiente: una sola request masiva ya tira el worker.
     */
    public function auditoria(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin'    => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ]);

        $inicio = isset($validated['fecha_inicio'])
            ? Carbon::parse($validated['fecha_inicio'])
            : null;

        $fin = isset($validated['fecha_fin'])
            ? Carbon::parse($validated['fecha_fin'])
            : null;

        if ($inicio !== null && $fin === null) {
            $fin = now();
        }
        if ($fin !== null && $inicio === null) {
            $inicio = $fin->copy()->subDays(90);
        }

        if ($inicio !== null && $fin !== null) {
            $this->validarRangoMaximo($inicio, $fin);
        }

        return Excel::download(
            new AuditoriaExport($inicio, $fin),
            'auditoria_' . now()->format('Ymd') . '.xlsx',
        );
    }

    /**
     * Impide rangos arbitrariamente grandes (DoS por export).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validarRangoMaximo(Carbon $inicio, Carbon $fin, int $diasMaximos = 90): void
    {
        if (abs((int) $inicio->diffInDays($fin)) > $diasMaximos) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fecha_fin' => "El rango máximo permitido es de {$diasMaximos} días.",
            ]);
        }
    }
}
