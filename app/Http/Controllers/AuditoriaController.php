<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaConsultaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditoriaController extends Controller
{
    public function __construct(private readonly AuditoriaConsultaService $service)
    {
    }

    public function index(Request $request): Response
    {
        $buscar = $request->string('buscar')->trim()->value() ?: null;
        $modulo = $request->string('modulo')->trim()->value() ?: null;
        $fecha  = $request->string('fecha')->trim()->value() ?: null;

        return Inertia::render('Auditoria/Index', [
            'registros' => $this->service->paginar($buscar, $modulo, $fecha),
            'modulos'   => $this->service->modulos(),
            'filtros'   => [
                'buscar' => $buscar,
                'modulo' => $modulo,
                'fecha'  => $fecha,
            ],
        ]);
    }
}
