<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoCompra;
use App\Enums\MotivoMovimiento;
use App\Enums\Rol;
use App\Enums\TipoMovimiento;
use App\Models\Compra;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\CompraService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del módulo de Compras (órdenes de compra a proveedor).
 *
 * Cubre:
 *  - Crear compra en PENDIENTE (no afecta stock)
 *  - Recibir mercadería: genera 1 lote por línea + 1 movimiento ENTRADA con
 *    motivo=COMPRA por línea, referencia_id apuntando a la compra
 *  - Editar/anular solo permitidos si estado=PENDIENTE
 *  - Anular no afecta stock
 *  - Re-recibir una compra ya RECIBIDA falla
 *  - Vendedor recibe 403 en todos los endpoints
 *  - Correlativo OC-N persistido en secuencias_compra
 */
class CompraTest extends TestCase
{
    use RefreshDatabase;

    private CompraService $servicio;
    private User $admin;
    private User $vendedor;
    private Proveedor $proveedor;
    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        /** @var CompraService $servicio */
        $this->servicio = app(CompraService::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Rol::ADMINISTRADOR->value);

        $this->vendedor = User::factory()->create();
        $this->vendedor->assignRole(Rol::VENDEDOR->value);

        $this->proveedor = Proveedor::factory()->create();
        $this->producto  = Producto::factory()->create(['activo' => true]);
    }

    private function datosCompra(int $cantidad = 10, float $precio = 5.50): array
    {
        return [
            'proveedor_id'  => $this->proveedor->id,
            'fecha_compra'  => now()->toDateString(),
            'observaciones' => 'Compra de prueba',
            'items' => [
                [
                    'producto_id'       => $this->producto->id,
                    'cantidad'          => $cantidad,
                    'precio_unitario'   => $precio,
                    'codigo_lote'       => 'LOTE-TEST-001',
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Crear
    // -------------------------------------------------------------------------

    public function test_crear_compra_arranca_en_pendiente_sin_afectar_stock(): void
    {
        $this->actingAs($this->admin);

        $compra = $this->servicio->crear($this->datosCompra(15), $this->admin->id);

        $this->assertSame(EstadoCompra::PENDIENTE, $compra->estado);
        $this->assertSame(15 * 5.5, (float) $compra->total);
        $this->assertCount(1, $compra->detalles);

        // Stock no se vio afectado: no hay lotes nuevos ni movimientos.
        $this->assertDatabaseCount('lotes', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_compra_recibe_correlativo_secuencial(): void
    {
        $this->actingAs($this->admin);

        $c1 = $this->servicio->crear($this->datosCompra(), $this->admin->id);
        $c2 = $this->servicio->crear($this->datosCompra(), $this->admin->id);

        $this->assertSame($c1->numero + 1, $c2->numero);
        $this->assertDatabaseHas('secuencias_compra', ['serie' => 'OC', 'ultimo_numero' => $c2->numero]);
    }

    // -------------------------------------------------------------------------
    // Recibir mercadería
    // -------------------------------------------------------------------------

    public function test_recibir_genera_lote_y_movimiento_compra(): void
    {
        $this->actingAs($this->admin);

        $compra = $this->servicio->crear($this->datosCompra(20, 3.0), $this->admin->id);
        $compra = $this->servicio->recibir($compra, $this->admin->id);

        $this->assertSame(EstadoCompra::RECIBIDA, $compra->estado);
        $this->assertNotNull($compra->recibida_en);
        $this->assertSame($this->admin->id, $compra->recibida_por);

        // 1 lote nuevo
        $this->assertDatabaseHas('lotes', [
            'producto_id'    => $this->producto->id,
            'proveedor_id'   => $this->proveedor->id,
            'codigo_lote'    => 'LOTE-TEST-001',
            'stock'          => 20,
            'precio_compra'  => '3.00',
        ]);

        // 1 movimiento ENTRADA + motivo=COMPRA + referencia a la compra
        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id'     => $this->producto->id,
            'tipo'            => TipoMovimiento::ENTRADA->value,
            'motivo'          => MotivoMovimiento::COMPRA->value,
            'cantidad'        => 20,
            'stock_anterior'  => 0,
            'stock_posterior' => 20,
            'referencia_tipo' => 'compra',
            'referencia_id'   => $compra->id,
        ]);
    }

    public function test_recibir_compra_ya_recibida_lanza_excepcion(): void
    {
        $this->actingAs($this->admin);

        $compra = $this->servicio->crear($this->datosCompra(), $this->admin->id);
        $this->servicio->recibir($compra, $this->admin->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no se puede recibir');

        $this->servicio->recibir($compra->fresh(), $this->admin->id);
    }

    // -------------------------------------------------------------------------
    // Editar y anular
    // -------------------------------------------------------------------------

    public function test_editar_compra_recibida_lanza_excepcion(): void
    {
        $this->actingAs($this->admin);

        $compra = $this->servicio->crear($this->datosCompra(), $this->admin->id);
        $this->servicio->recibir($compra, $this->admin->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no se puede modificar');

        $this->servicio->actualizar($compra->fresh(), $this->datosCompra(50, 9.0));
    }

    public function test_anular_compra_pendiente_no_afecta_stock(): void
    {
        $this->actingAs($this->admin);

        $compra = $this->servicio->crear($this->datosCompra(), $this->admin->id);
        $this->servicio->anular($compra, 'Proveedor canceló envío', $this->admin->id);

        $compra->refresh();

        $this->assertSame(EstadoCompra::ANULADA, $compra->estado);
        $this->assertSame('Proveedor canceló envío', $compra->motivo_anulacion);
        $this->assertDatabaseCount('lotes', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_anular_compra_recibida_lanza_excepcion(): void
    {
        $this->actingAs($this->admin);

        $compra = $this->servicio->crear($this->datosCompra(), $this->admin->id);
        $this->servicio->recibir($compra, $this->admin->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no se puede anular');

        $this->servicio->anular($compra->fresh(), 'Intento de anulación', $this->admin->id);
    }

    // -------------------------------------------------------------------------
    // HTTP / RBAC
    // -------------------------------------------------------------------------

    public function test_admin_puede_listar_compras(): void
    {
        $this->actingAs($this->admin);
        $this->get(route('compras.index'))->assertStatus(200);
    }

    public function test_vendedor_recibe_403_al_listar_compras(): void
    {
        $this->actingAs($this->vendedor);
        $this->get(route('compras.index'))->assertStatus(403);
    }

    public function test_vendedor_recibe_403_al_crear_compra(): void
    {
        $this->actingAs($this->vendedor);
        $this->post(route('compras.store'), $this->datosCompra())->assertStatus(403);
    }

    public function test_admin_puede_recibir_via_endpoint(): void
    {
        $this->actingAs($this->admin);

        $compra = $this->servicio->crear($this->datosCompra(), $this->admin->id);

        $this->put(route('compras.recibir', $compra))
            ->assertRedirect();

        $this->assertSame(
            EstadoCompra::RECIBIDA,
            Compra::find($compra->id)->estado,
        );
    }
}
