<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'indicadores' => $this->service->indicadoresDelDia(),
            'stockBajo'   => $this->service->productosStockBajo(),
            'porVencer'   => $this->service->productosPorVencer(),
        ]);
    }
}
