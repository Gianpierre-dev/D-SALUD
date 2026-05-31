<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoCaja;
use App\Enums\Rol;
use App\Models\Caja;
use App\Models\Empresa;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Services\CajaService;
use App\Services\VentaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del módulo de Cajas (turno operativo + cuadre).
 *
 * Cubre:
 *  - Abrir / cerrar happy path
 *  - Doble apertura rechazada
 *  - Cierre con diferencia positiva (sobrante), negativa (faltante) y cero
 *  - Cierre suma solo ventas COMPLETADAS del usuario en el periodo
 *  - POS rechaza store si el usuario no tiene caja abierta
 *  - Vendedor solo accede a sus propias cajas (no a las de otros)
 *  - Reporte Z PDF solo para cajas CERRADAS
 */
class CajaTest extends TestCase
{
    use RefreshDatabase;

    private CajaService $servicio;
    private VentaService $ventaServicio;
    private User $vendedor;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // Empresa requerida por reporteZ.
        Empresa::firstOrCreate(['id' => 1], [
            'razon_social' => "Botica D'Salud S.A.C.",
            'ruc'          => '20600000001',
            'direccion'    => 'Lima',
        ]);

        /** @var CajaService $servicio */
        $this->servicio = app(CajaService::class);
        /** @var VentaService $ventas */
        $this->ventaServicio = app(VentaService::class);

        $this->vendedor = User::factory()->create();
        $this->vendedor->assignRole(Rol::VENDEDOR->value);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Rol::ADMINISTRADOR->value);
    }

    // -------------------------------------------------------------------------
    // Service: abrir
    // -------------------------------------------------------------------------

    public function test_abrir_caja_persiste_estado_abierta_y_monto(): void
    {
        $caja = $this->servicio->abrir($this->vendedor->id, 100.50, 'Inicio del turno');

        $this->assertSame(EstadoCaja::ABIERTA, $caja->estado);
        $this->assertSame('100.50', (string) $caja->monto_apertura);
        $this->assertSame($this->vendedor->id, $caja->user_id);
    }

    public function test_no_se_puede_abrir_caja_si_ya_hay_una_abierta(): void
    {
        $this->servicio->abrir($this->vendedor->id, 50.00);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('caja abierta');

        $this->servicio->abrir($this->vendedor->id, 100.00);
    }

    // -------------------------------------------------------------------------
    // Service: cerrar y cuadre
    // -------------------------------------------------------------------------

    public function test_cerrar_caja_calcula_total_ventas_esperado_y_diferencia_cero(): void
    {
        $this->actingAs($this->vendedor);

        $caja = $this->servicio->abrir($this->vendedor->id, 100.00);

        $producto = Producto::factory()->create(['precio_venta' => 25.00, 'activo' => true]);
        Lote::factory()->vigente()->conStock(20)->create(['producto_id' => $producto->id]);

        // 2 ventas de 25 c/u = S/ 50
        $this->ventaServicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id,
        );
        $this->ventaServicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id,
        );

        // Esperado = 100 + 50 = 150. Si cuenta 150, diferencia = 0.
        $cerrada = $this->servicio->cerrar($caja->fresh(), 150.00, $this->vendedor->id);

        $this->assertSame(EstadoCaja::CERRADA, $cerrada->estado);
        $this->assertSame('50.00',  (string) $cerrada->total_ventas);
        $this->assertSame('150.00', (string) $cerrada->total_esperado);
        $this->assertSame('0.00',   (string) $cerrada->diferencia);
    }

    public function test_cerrar_caja_con_sobrante_devuelve_diferencia_positiva(): void
    {
        $this->actingAs($this->vendedor);

        $caja = $this->servicio->abrir($this->vendedor->id, 100.00);
        // No hay ventas → esperado = 100. Si cuenta 105 → sobrante +5.

        $cerrada = $this->servicio->cerrar($caja->fresh(), 105.00, $this->vendedor->id);

        $this->assertSame('5.00', (string) $cerrada->diferencia);
    }

    public function test_cerrar_caja_con_faltante_devuelve_diferencia_negativa(): void
    {
        $this->actingAs($this->vendedor);

        $caja = $this->servicio->abrir($this->vendedor->id, 100.00);
        // Esperado = 100. Si cuenta 90 → faltante -10.

        $cerrada = $this->servicio->cerrar($caja->fresh(), 90.00, $this->vendedor->id);

        $this->assertSame('-10.00', (string) $cerrada->diferencia);
    }

    public function test_cerrar_caja_ignora_ventas_anuladas(): void
    {
        $this->actingAs($this->vendedor);

        $caja = $this->servicio->abrir($this->vendedor->id, 100.00);

        $producto = Producto::factory()->create(['precio_venta' => 30.00, 'activo' => true]);
        Lote::factory()->vigente()->conStock(10)->create(['producto_id' => $producto->id]);

        $venta = $this->ventaServicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id,
        );

        // Anular la única venta: la venta NO debe contar en el total del turno.
        $this->ventaServicio->anular($venta, 'Error de tipeo', $this->admin->id);

        $cerrada = $this->servicio->cerrar($caja->fresh(), 100.00, $this->vendedor->id);

        $this->assertSame('0.00',   (string) $cerrada->total_ventas);
        $this->assertSame('100.00', (string) $cerrada->total_esperado);
        $this->assertSame('0.00',   (string) $cerrada->diferencia);
    }

    public function test_no_se_puede_cerrar_caja_ya_cerrada(): void
    {
        $caja = $this->servicio->abrir($this->vendedor->id, 50.00);
        $this->servicio->cerrar($caja->fresh(), 50.00, $this->vendedor->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ya está cerrada');

        $this->servicio->cerrar($caja->fresh(), 50.00, $this->vendedor->id);
    }

    public function test_caja_abierta_de_devuelve_la_caja_activa_o_null(): void
    {
        $this->assertNull($this->servicio->cajaAbiertaDe($this->vendedor->id));

        $caja = $this->servicio->abrir($this->vendedor->id, 50.00);

        $activa = $this->servicio->cajaAbiertaDe($this->vendedor->id);
        $this->assertNotNull($activa);
        $this->assertSame($caja->id, $activa->id);

        $this->servicio->cerrar($caja->fresh(), 50.00, $this->vendedor->id);
        $this->assertNull($this->servicio->cajaAbiertaDe($this->vendedor->id));
    }

    // -------------------------------------------------------------------------
    // HTTP / RBAC
    // -------------------------------------------------------------------------

    public function test_vendedor_puede_abrir_su_caja_via_endpoint(): void
    {
        $this->actingAs($this->vendedor);

        $this->post(route('cajas.store'), [
            'monto_apertura' => 80.00,
        ])->assertRedirect();

        $this->assertDatabaseHas('cajas', [
            'user_id'        => $this->vendedor->id,
            'monto_apertura' => '80.00',
            'estado'         => 'ABIERTA',
        ]);
    }

    public function test_vendedor_no_ve_la_caja_de_otro_usuario(): void
    {
        $otro = User::factory()->create();
        $otro->assignRole(Rol::VENDEDOR->value);

        $cajaAjena = $this->servicio->abrir($otro->id, 50.00);

        $this->actingAs($this->vendedor);
        $this->get(route('cajas.show', $cajaAjena))->assertStatus(403);
    }

    public function test_admin_si_ve_la_caja_de_otro_usuario(): void
    {
        $cajaDelVendedor = $this->servicio->abrir($this->vendedor->id, 50.00);

        $this->actingAs($this->admin);
        $this->get(route('cajas.show', $cajaDelVendedor))->assertStatus(200);
    }

    public function test_reporte_z_solo_disponible_si_la_caja_esta_cerrada(): void
    {
        $this->actingAs($this->vendedor);

        $caja = $this->servicio->abrir($this->vendedor->id, 50.00);

        // ABIERTA: 409
        $this->get(route('cajas.reporteZ', $caja))->assertStatus(409);

        $this->servicio->cerrar($caja->fresh(), 50.00, $this->vendedor->id);

        // CERRADA: PDF
        $respuesta = $this->get(route('cajas.reporteZ', $caja));
        $respuesta->assertStatus(200);
        $this->assertSame('application/pdf', $respuesta->headers->get('Content-Type'));
    }

    // -------------------------------------------------------------------------
    // POS bloqueado sin caja
    // -------------------------------------------------------------------------

    public function test_post_venta_falla_sin_caja_abierta(): void
    {
        $this->actingAs($this->vendedor);

        $producto = Producto::factory()->create(['precio_venta' => 10.00, 'activo' => true]);
        Lote::factory()->vigente()->conStock(10)->create(['producto_id' => $producto->id]);

        // Sin caja abierta: el POST debe redirigir con error.
        $response = $this->post(route('ventas.store'), [
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_post_venta_funciona_con_caja_abierta(): void
    {
        $this->actingAs($this->vendedor);

        $this->servicio->abrir($this->vendedor->id, 100.00);

        $producto = Producto::factory()->create(['precio_venta' => 10.00, 'activo' => true]);
        Lote::factory()->vigente()->conStock(10)->create(['producto_id' => $producto->id]);

        $response = $this->post(route('ventas.store'), [
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ventas', 1);
    }
}
