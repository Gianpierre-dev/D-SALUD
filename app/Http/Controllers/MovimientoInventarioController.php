<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MotivoMovimiento;
use App\Enums\TipoMovimiento;
use App\Http\Requests\Inventario\StoreMovimientoInventarioRequest;
use App\Models\Lote;
use App\Models\Producto;
use App\Services\MovimientoInventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MovimientoInventarioController extends Controller
{
    public function __construct(private readonly MovimientoInventarioService $service)
    {
    }

    /**
     * Listado paginado del kardex con filtros.
     * Pasa también la lista de lotes y los motivos manuales para el modal.
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'producto_id' => $request->integer('producto_id') ?: null,
            'lote_id'     => $request->integer('lote_id') ?: null,
            'tipo'        => $request->string('tipo')->trim()->value() ?: null,
            'motivo'      => $request->string('motivo')->trim()->value() ?: null,
            'desde'       => $request->string('desde')->trim()->value() ?: null,
            'hasta'       => $request->string('hasta')->trim()->value() ?: null,
        ];

        return Inertia::render('Inventario/Movimientos/Index', [
            'movimientos' => $this->service->paginar($filtros),
            'filtros'     => $filtros,
            'productos'   => Producto::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
            'lotes'       => Lote::query()
                ->with('producto:id,nombre')
                ->orderBy('codigo_lote')
                ->get(['id', 'codigo_lote', 'producto_id', 'stock']),
            'motivosManuales' => array_map(
                static fn (MotivoMovimiento $m) => ['value' => $m->value, 'label' => $m->etiqueta()],
                MotivoMovimiento::manuales(),
            ),
            'tipos' => array_map(
                static fn (TipoMovimiento $t) => $t->value,
                TipoMovimiento::cases(),
            ),
        ]);
    }

    /**
     * Registro manual de movimiento (mermas, ajustes, vencimientos, devoluciones).
     */
    public function store(StoreMovimientoInventarioRequest $request): RedirectResponse
    {
        $datos  = $request->validated();
        $motivo = MotivoMovimiento::from($datos['motivo']);
        $lote   = Lote::findOrFail($datos['lote_id']);

        try {
            if ($motivo->tipo() === TipoMovimiento::ENTRADA) {
                $this->service->registrarEntrada(
                    $lote,
                    $motivo,
                    (int) $datos['cantidad'],
                    $datos['observacion'] ?? null,
                );
            } else {
                $this->service->registrarSalida(
                    $lote,
                    $motivo,
                    (int) $datos['cantidad'],
                    $datos['observacion'] ?? null,
                );
            }
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Movimiento registrado correctamente.');
    }
}
