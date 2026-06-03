<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Services\CajaService;
use App\Services\DashboardService;
use App\Services\VentaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de los datasets de gráficos del dashboard.
 */
class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $dashboard;
    private VentaService $ventas;
    private User $vendedor;
    private int $cajaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->dashboard = app(DashboardService::class);
        $this->ventas    = app(VentaService::class);

        $this->vendedor = User::factory()->create();
        $this->vendedor->assignRole(Rol::VENDEDOR->value);
        $this->actingAs($this->vendedor);

        $this->cajaId = app(CajaService::class)->abrir($this->vendedor->id, 0.0)->id;
    }

    private function venderHoy(float $precio, int $cantidad, string $medio = 'EFECTIVO'): void
    {
        $producto = Producto::factory()->state(['precio_venta' => $precio, 'activo' => true])->create();
        Lote::factory()->vigente()->conStock(1000)->create(['producto_id' => $producto->id]);

        $this->ventas->registrar(
            [['producto_id' => $producto->id, 'cantidad' => $cantidad]],
            $this->vendedor->id,
            null,
            [['medio_pago' => $medio, 'monto' => $precio * $cantidad]],
            $this->cajaId,
        );
    }

    public function test_ventas_ultimos_dias_devuelve_serie_completa_con_ceros(): void
    {
        $serie = $this->dashboard->ventasUltimosDias(14);

        // Siempre 14 días, incluso sin ventas.
        $this->assertCount(14, $serie);
        $this->assertArrayHasKey('fecha', $serie[0]);
        $this->assertArrayHasKey('etiqueta', $serie[0]);
        $this->assertArrayHasKey('total', $serie[0]);

        // El último día es hoy y sin ventas arranca en 0.
        $this->assertSame(0.0, $serie[13]['total']);
    }

    public function test_ventas_ultimos_dias_suma_el_total_de_hoy(): void
    {
        $this->venderHoy(50.0, 2); // 100

        $serie = $this->dashboard->ventasUltimosDias(14);

        // El último elemento (hoy) debe reflejar la venta.
        $this->assertSame(100.0, $serie[13]['total']);
    }

    public function test_top_productos_del_mes_ordena_por_cantidad(): void
    {
        $this->venderHoy(10.0, 5); // producto A: 5 unidades
        $this->venderHoy(10.0, 9); // producto B: 9 unidades
        $this->venderHoy(10.0, 2); // producto C: 2 unidades

        $top = $this->dashboard->topProductosDelMes(5);

        $this->assertCount(3, $top);
        $this->assertSame(9, $top[0]['cantidad']); // el más vendido primero
        $this->assertSame(5, $top[1]['cantidad']);
        $this->assertSame(2, $top[2]['cantidad']);
    }

    public function test_ventas_por_medio_del_mes_agrupa_y_excluye_ceros(): void
    {
        $this->venderHoy(40.0, 1, 'EFECTIVO'); // 40 efectivo
        $this->venderHoy(60.0, 1, 'YAPE');     // 60 yape

        $porMedio = $this->dashboard->ventasPorMedioDelMes();

        // Solo 2 medios con monto (efectivo, yape); el resto se excluye.
        $this->assertCount(2, $porMedio);

        $totales = collect($porMedio)->pluck('total', 'medio');
        $this->assertSame(40.0, $totales['Efectivo']);
        $this->assertSame(60.0, $totales['Yape']);
    }
}
