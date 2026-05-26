<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\VentaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas HTTP del módulo Lotes (inventario).
 *
 * Cubre: CRUD completo (índice, crear, actualizar, eliminar),
 * validaciones de formulario (fecha_vencimiento, codigo_lote único por producto)
 * y control de acceso por rol (RBAC).
 */
class LoteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Rol::ADMINISTRADOR->value);

        $this->vendedor = User::factory()->create();
        $this->vendedor->assignRole(Rol::VENDEDOR->value);
    }

    /**
     * Datos válidos mínimos para crear un lote.
     *
     * @return array<string, mixed>
     */
    private function datosLoteValidos(?int $productoId = null): array
    {
        $productoId ??= Producto::factory()->create(['activo' => true])->id;

        return [
            'producto_id'       => $productoId,
            'proveedor_id'      => null,
            'codigo_lote'       => 'LOT-TEST01',
            'fecha_vencimiento' => now()->addYear()->format('Y-m-d'),
            'stock'             => 50,
            'precio_compra'     => 10.00,
        ];
    }

    // -------------------------------------------------------------------------
    // Índice
    // -------------------------------------------------------------------------

    public function test_admin_puede_ver_el_indice_de_lotes(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('lotes.index'));

        $response->assertStatus(200);
    }

    public function test_vendedor_puede_ver_el_indice_de_lotes(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->get(route('lotes.index'));

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Crear
    // -------------------------------------------------------------------------

    public function test_admin_puede_crear_lote(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosLoteValidos();

        $response = $this->post(route('lotes.store'), $datos);

        $response->assertRedirect();
        $this->assertDatabaseHas('lotes', ['codigo_lote' => 'LOT-TEST01']);
    }

    public function test_admin_puede_crear_lote_con_proveedor(): void
    {
        $proveedor = Proveedor::factory()->create();
        $this->actingAs($this->admin);
        $datos = $this->datosLoteValidos();
        $datos['proveedor_id'] = $proveedor->id;

        $response = $this->post(route('lotes.store'), $datos);

        $response->assertRedirect();
        $this->assertDatabaseHas('lotes', [
            'codigo_lote'  => 'LOT-TEST01',
            'proveedor_id' => $proveedor->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Validaciones — store
    // -------------------------------------------------------------------------

    public function test_crear_lote_falla_con_producto_inexistente(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosLoteValidos();
        $datos['producto_id'] = 99999;

        $response = $this->post(route('lotes.store'), $datos);

        $response->assertSessionHasErrors('producto_id');
    }

    public function test_crear_lote_falla_con_fecha_de_vencimiento_pasada(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosLoteValidos();
        $datos['fecha_vencimiento'] = now()->subDay()->format('Y-m-d');

        $response = $this->post(route('lotes.store'), $datos);

        $response->assertSessionHasErrors('fecha_vencimiento');
    }

    public function test_crear_lote_acepta_fecha_de_vencimiento_igual_a_hoy(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosLoteValidos();
        $datos['fecha_vencimiento'] = now()->format('Y-m-d');
        $datos['codigo_lote'] = 'LOT-TODAY';

        $response = $this->post(route('lotes.store'), $datos);

        $response->assertRedirect();
        $this->assertDatabaseHas('lotes', ['codigo_lote' => 'LOT-TODAY']);
    }

    public function test_crear_lote_falla_con_codigo_lote_duplicado_para_el_mismo_producto(): void
    {
        $producto = Producto::factory()->create(['activo' => true]);
        // Lote existente con el mismo codigo_lote y mismo producto
        Lote::factory()->create([
            'producto_id'  => $producto->id,
            'codigo_lote'  => 'LOT-DUPL',
        ]);

        $this->actingAs($this->admin);
        $datos = $this->datosLoteValidos($producto->id);
        $datos['codigo_lote'] = 'LOT-DUPL';

        $response = $this->post(route('lotes.store'), $datos);

        $response->assertSessionHasErrors('codigo_lote');
    }

    public function test_crear_lote_permite_codigo_lote_igual_para_diferente_producto(): void
    {
        $producto1 = Producto::factory()->create(['activo' => true]);
        $producto2 = Producto::factory()->create(['activo' => true]);

        // Lote con el código en producto1
        Lote::factory()->create([
            'producto_id' => $producto1->id,
            'codigo_lote' => 'LOT-SHARED',
        ]);

        $this->actingAs($this->admin);

        // El mismo codigo_lote en producto2 debe ser válido
        $response = $this->post(route('lotes.store'), [
            'producto_id'       => $producto2->id,
            'proveedor_id'      => null,
            'codigo_lote'       => 'LOT-SHARED',
            'fecha_vencimiento' => now()->addYear()->format('Y-m-d'),
            'stock'             => 20,
            'precio_compra'     => 5.00,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lotes', [
            'producto_id' => $producto2->id,
            'codigo_lote' => 'LOT-SHARED',
        ]);
    }

    public function test_crear_lote_falla_con_stock_negativo(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosLoteValidos();
        $datos['stock'] = -1;

        $response = $this->post(route('lotes.store'), $datos);

        $response->assertSessionHasErrors('stock');
    }

    public function test_crear_lote_acepta_stock_cero(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosLoteValidos();
        $datos['stock'] = 0;
        $datos['codigo_lote'] = 'LOT-ZERO';

        $response = $this->post(route('lotes.store'), $datos);

        $response->assertRedirect();
        $this->assertDatabaseHas('lotes', ['codigo_lote' => 'LOT-ZERO', 'stock' => 0]);
    }

    public function test_crear_lote_falla_con_fecha_vencimiento_vacia(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosLoteValidos();
        $datos['fecha_vencimiento'] = '';

        $response = $this->post(route('lotes.store'), $datos);

        $response->assertSessionHasErrors('fecha_vencimiento');
    }

    // -------------------------------------------------------------------------
    // Actualizar
    // -------------------------------------------------------------------------

    public function test_admin_puede_actualizar_lote(): void
    {
        $lote = Lote::factory()->vigente()->create();

        $this->actingAs($this->admin);

        $response = $this->put(route('lotes.update', $lote), [
            'producto_id'       => $lote->producto_id,
            'proveedor_id'      => null,
            'codigo_lote'       => $lote->codigo_lote,
            'fecha_vencimiento' => now()->addMonths(6)->format('Y-m-d'),
            'stock'             => 99,
            'precio_compra'     => 20.00,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lotes', ['id' => $lote->id, 'stock' => 99]);
    }

    public function test_actualizar_lote_permite_fecha_pasada_para_correccion(): void
    {
        // En update, el FormRequest no exige after_or_equal:today (permite corrección de vencidos)
        $lote = Lote::factory()->vigente()->create();

        $this->actingAs($this->admin);

        $response = $this->put(route('lotes.update', $lote), [
            'producto_id'       => $lote->producto_id,
            'proveedor_id'      => null,
            'codigo_lote'       => $lote->codigo_lote,
            'fecha_vencimiento' => now()->subYear()->format('Y-m-d'),
            'stock'             => $lote->stock,
            'precio_compra'     => $lote->precio_compra,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lotes', ['id' => $lote->id]);
    }

    public function test_actualizar_lote_falla_con_codigo_lote_duplicado_de_otro_lote_del_mismo_producto(): void
    {
        $producto = Producto::factory()->create(['activo' => true]);
        Lote::factory()->create(['producto_id' => $producto->id, 'codigo_lote' => 'LOT-EXISTE']);
        $lote = Lote::factory()->create(['producto_id' => $producto->id, 'codigo_lote' => 'LOT-ESTE']);

        $this->actingAs($this->admin);

        $response = $this->put(route('lotes.update', $lote), [
            'producto_id'       => $producto->id,
            'proveedor_id'      => null,
            'codigo_lote'       => 'LOT-EXISTE',
            'fecha_vencimiento' => now()->addYear()->format('Y-m-d'),
            'stock'             => 10,
            'precio_compra'     => 5.00,
        ]);

        $response->assertSessionHasErrors('codigo_lote');
    }

    // -------------------------------------------------------------------------
    // Eliminar
    // -------------------------------------------------------------------------

    public function test_admin_puede_eliminar_lote_sin_ventas_asociadas(): void
    {
        $lote = Lote::factory()->vigente()->create();

        $this->actingAs($this->admin);

        $response = $this->delete(route('lotes.destroy', $lote));

        $response->assertRedirect();
        $this->assertDatabaseMissing('lotes', ['id' => $lote->id]);
    }

    public function test_no_se_puede_eliminar_lote_con_ventas_asociadas(): void
    {
        // Crear un lote con venta real usando VentaService para que haya detalle_ventas
        $admin = $this->admin;
        $this->actingAs($admin);

        $producto = Producto::factory()->create(['precio_venta' => 10.00, 'activo' => true]);
        $lote = Lote::factory()->vigente()->conStock(50)->create(['producto_id' => $producto->id]);

        /** @var VentaService $ventaService */
        $ventaService = app(VentaService::class);
        $ventaService->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $admin->id
        );

        // Ahora intentar eliminar el lote debe fallar (respuesta redirige con error)
        $response = $this->delete(route('lotes.destroy', $lote));

        $response->assertRedirect();
        // El lote NO debe haberse eliminado
        $this->assertDatabaseHas('lotes', ['id' => $lote->id]);
    }

    // -------------------------------------------------------------------------
    // RBAC — Vendedor no puede crear / actualizar / eliminar
    // -------------------------------------------------------------------------

    public function test_vendedor_no_puede_crear_lote(): void
    {
        $this->actingAs($this->vendedor);
        $datos = $this->datosLoteValidos();

        $response = $this->post(route('lotes.store'), $datos);

        $response->assertStatus(403);
    }

    public function test_vendedor_no_puede_actualizar_lote(): void
    {
        $lote = Lote::factory()->vigente()->create();

        $this->actingAs($this->vendedor);

        $response = $this->put(route('lotes.update', $lote), [
            'producto_id'       => $lote->producto_id,
            'proveedor_id'      => null,
            'codigo_lote'       => $lote->codigo_lote,
            'fecha_vencimiento' => now()->addYear()->format('Y-m-d'),
            'stock'             => 1,
            'precio_compra'     => 1.00,
        ]);

        $response->assertStatus(403);
    }

    public function test_vendedor_no_puede_eliminar_lote(): void
    {
        $lote = Lote::factory()->vigente()->create();

        $this->actingAs($this->vendedor);

        $response = $this->delete(route('lotes.destroy', $lote));

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Usuario no autenticado
    // -------------------------------------------------------------------------

    public function test_usuario_no_autenticado_es_redirigido_desde_lotes(): void
    {
        $response = $this->get(route('lotes.index'));

        $response->assertRedirect(route('login'));
    }
}
