<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MotivoMovimiento;
use App\Enums\Rol;
use App\Enums\TipoMovimiento;
use App\Models\Lote;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\User;
use App\Services\MovimientoInventarioService;
use App\Services\VentaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del kardex (movimientos de inventario).
 *
 * Cubre:
 * - Refactor VentaService: cada venta genera N movimientos SALIDA con motivo=VENTA
 * - Anulación de venta genera ENTRADAS con motivo=ANULACION_VENTA
 * - CRUD manual de movimientos (admin only)
 * - Validación: cantidad <= 0 rechaza, salida sobre stock 0 rechaza
 * - RBAC: vendedor recibe 403 en /inventario/movimientos
 */
class MovimientoInventarioTest extends TestCase
{
    use RefreshDatabase;

    private MovimientoInventarioService $servicioKardex;
    private VentaService $servicioVenta;
    private User $admin;
    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        /** @var MovimientoInventarioService $kardex */
        $this->servicioKardex = app(MovimientoInventarioService::class);

        /** @var VentaService $ventas */
        $this->servicioVenta = app(VentaService::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Rol::ADMINISTRADOR->value);

        $this->vendedor = User::factory()->create();
        $this->vendedor->assignRole(Rol::VENDEDOR->value);
    }

    // -------------------------------------------------------------------------
    // Refactor VentaService: venta genera kardex automáticamente
    // -------------------------------------------------------------------------

    public function test_venta_genera_movimientos_salida_con_motivo_venta(): void
    {
        $this->actingAs($this->vendedor);

        $producto = Producto::factory()->create(['precio_venta' => 10.00, 'activo' => true]);
        $lote     = Lote::factory()->vigente()->conStock(50)->create(['producto_id' => $producto->id]);

        $venta = $this->servicioVenta->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 3]],
            $this->vendedor->id,
        );

        $this->assertDatabaseHas('movimientos_inventario', [
            'lote_id'         => $lote->id,
            'producto_id'     => $producto->id,
            'tipo'            => TipoMovimiento::SALIDA->value,
            'motivo'          => MotivoMovimiento::VENTA->value,
            'cantidad'        => 3,
            'stock_anterior'  => 50,
            'stock_posterior' => 47,
            'referencia_tipo' => 'venta',
            'referencia_id'   => $venta->id,
        ]);

        $this->assertSame(47, $lote->fresh()->stock);
    }

    public function test_anular_venta_genera_movimientos_entrada_con_motivo_anulacion(): void
    {
        $this->actingAs($this->vendedor);

        $producto = Producto::factory()->create(['precio_venta' => 10.00, 'activo' => true]);
        $lote     = Lote::factory()->vigente()->conStock(50)->create(['producto_id' => $producto->id]);

        $venta = $this->servicioVenta->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 5]],
            $this->vendedor->id,
        );

        $this->servicioVenta->anular($venta, 'Cliente arrepentido', $this->admin->id);

        $this->assertDatabaseHas('movimientos_inventario', [
            'lote_id'         => $lote->id,
            'tipo'            => TipoMovimiento::ENTRADA->value,
            'motivo'          => MotivoMovimiento::ANULACION_VENTA->value,
            'cantidad'        => 5,
            'stock_posterior' => 50, // Restituido al original
            'referencia_tipo' => 'venta',
            'referencia_id'   => $venta->id,
        ]);

        $this->assertSame(50, $lote->fresh()->stock);
    }

    // -------------------------------------------------------------------------
    // Service core: validaciones
    // -------------------------------------------------------------------------

    public function test_salida_con_stock_insuficiente_lanza_excepcion(): void
    {
        $this->actingAs($this->admin);

        $producto = Producto::factory()->create();
        $lote     = Lote::factory()->vigente()->conStock(5)->create(['producto_id' => $producto->id]);

        $this->expectException(\RuntimeException::class);

        $this->servicioKardex->registrarSalida(
            $lote,
            MotivoMovimiento::MERMA,
            10,
            'Intento de salida con stock insuficiente',
        );
    }

    public function test_cantidad_cero_o_negativa_lanza_invalid_argument(): void
    {
        $this->actingAs($this->admin);

        $producto = Producto::factory()->create();
        $lote     = Lote::factory()->vigente()->conStock(10)->create(['producto_id' => $producto->id]);

        $this->expectException(\InvalidArgumentException::class);

        $this->servicioKardex->registrarEntrada(
            $lote,
            MotivoMovimiento::AJUSTE_POSITIVO,
            0,
        );
    }

    public function test_motivo_incompatible_con_tipo_lanza_invalid_argument(): void
    {
        $this->actingAs($this->admin);

        $producto = Producto::factory()->create();
        $lote     = Lote::factory()->vigente()->conStock(10)->create(['producto_id' => $producto->id]);

        // MERMA es de SALIDA — no debería poder usarse para una entrada.
        $this->expectException(\InvalidArgumentException::class);

        $this->servicioKardex->registrarEntrada(
            $lote,
            MotivoMovimiento::MERMA,
            5,
        );
    }

    // -------------------------------------------------------------------------
    // CRUD HTTP
    // -------------------------------------------------------------------------

    public function test_admin_puede_listar_movimientos(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('inventario.movimientos.index'))->assertStatus(200);
    }

    public function test_vendedor_recibe_403_al_intentar_listar_movimientos(): void
    {
        $this->actingAs($this->vendedor);

        $this->get(route('inventario.movimientos.index'))->assertStatus(403);
    }

    public function test_admin_registra_merma_manual(): void
    {
        $this->actingAs($this->admin);

        $producto = Producto::factory()->create();
        $lote     = Lote::factory()->vigente()->conStock(20)->create(['producto_id' => $producto->id]);

        $response = $this->post(route('inventario.movimientos.store'), [
            'lote_id'     => $lote->id,
            'motivo'      => MotivoMovimiento::MERMA->value,
            'cantidad'    => 3,
            'observacion' => 'Producto dañado en almacén',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('movimientos_inventario', [
            'lote_id'   => $lote->id,
            'tipo'      => TipoMovimiento::SALIDA->value,
            'motivo'    => MotivoMovimiento::MERMA->value,
            'cantidad'  => 3,
        ]);
        $this->assertSame(17, $lote->fresh()->stock);
    }

    public function test_no_se_permite_registrar_motivo_automatico_desde_endpoint(): void
    {
        $this->actingAs($this->admin);

        $producto = Producto::factory()->create();
        $lote     = Lote::factory()->vigente()->conStock(20)->create(['producto_id' => $producto->id]);

        // VENTA es un motivo automático: no debe poder registrarse manualmente.
        $response = $this->post(route('inventario.movimientos.store'), [
            'lote_id'  => $lote->id,
            'motivo'   => MotivoMovimiento::VENTA->value,
            'cantidad' => 1,
        ]);

        $response->assertSessionHasErrors('motivo');
        $this->assertSame(20, $lote->fresh()->stock);
    }

    public function test_kardex_de_lote_devuelve_movimientos_ordenados(): void
    {
        $this->actingAs($this->admin);

        $producto = Producto::factory()->create();
        $lote     = Lote::factory()->vigente()->conStock(100)->create(['producto_id' => $producto->id]);

        $this->servicioKardex->registrarSalida($lote, MotivoMovimiento::MERMA, 10);
        $this->servicioKardex->registrarEntrada($lote, MotivoMovimiento::AJUSTE_POSITIVO, 5);
        $this->servicioKardex->registrarSalida($lote, MotivoMovimiento::VENCIMIENTO, 2);

        $kardex = $this->servicioKardex->kardexDeLote($lote->id);

        $this->assertCount(3, $kardex);
        $this->assertSame(MotivoMovimiento::MERMA, $kardex[0]->motivo);
        $this->assertSame(MotivoMovimiento::AJUSTE_POSITIVO, $kardex[1]->motivo);
        $this->assertSame(MotivoMovimiento::VENCIMIENTO, $kardex[2]->motivo);

        // Stock final esperado: 100 - 10 + 5 - 2 = 93
        $this->assertSame(93, $lote->fresh()->stock);
    }
}
