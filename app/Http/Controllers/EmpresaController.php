<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Empresa\UpdateEmpresaRequest;
use App\Services\EmpresaService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaController extends Controller
{
    public function __construct(private readonly EmpresaService $service)
    {
    }

    public function edit(): Response
    {
        return Inertia::render('Configuracion/Edit', [
            'empresa' => $this->service->obtener(),
            'logoUrl' => $this->service->urlLogo(),
        ]);
    }

    public function update(UpdateEmpresaRequest $request): RedirectResponse
    {
        $this->service->actualizar(
            $request->safe()->except('logo'),
            $request->file('logo'),
        );

        return redirect()->route('configuracion.edit')
            ->with('success', 'Configuración de empresa actualizada correctamente.');
    }
}
