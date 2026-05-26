<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas HTTP del módulo Productos.
 *
 * Cubre: CRUD completo (índice, crear, actualizar, eliminar),
 * validaciones de formulario y control de acceso por rol (RBAC).
 */
class ProductoTest extends TestCase
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
     * Datos válidos mínimos para crear un producto.
     *
     * @return array<string, mixed>
     */
    private function datosProductoValidos(?int $categoriaId = null): array
    {
        $categoriaId ??= Categoria::factory()->create()->id;

        return [
            'codigo'        => 'MED-0001',
            'nombre'        => 'Paracetamol 500mg',
            'categoria_id'  => $categoriaId,
            'unidad_medida' => 'tableta',
            'precio_venta'  => 5.50,
            'stock_minimo'  => 10,
            'activo'        => true,
        ];
    }

    // -------------------------------------------------------------------------
    // Índice
    // -------------------------------------------------------------------------

    public function test_admin_puede_ver_el_indice_de_productos(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('productos.index'));

        $response->assertStatus(200);
    }

    public function test_vendedor_puede_ver_el_indice_de_productos(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->get(route('productos.index'));

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Crear
    // -------------------------------------------------------------------------

    public function test_admin_puede_crear_producto(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosProductoValidos();

        $response = $this->post(route('productos.store'), $datos);

        $response->assertRedirect();
        $this->assertDatabaseHas('productos', ['codigo' => 'MED-0001']);
    }

    public function test_admin_puede_crear_producto_sin_laboratorio(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosProductoValidos();
        unset($datos['laboratorio']);

        $response = $this->post(route('productos.store'), $datos);

        $response->assertRedirect();
        $this->assertDatabaseHas('productos', ['codigo' => 'MED-0001']);
    }

    // -------------------------------------------------------------------------
    // Validaciones — store
    // -------------------------------------------------------------------------

    public function test_crear_producto_falla_con_codigo_vacio(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosProductoValidos();
        $datos['codigo'] = '';

        $response = $this->post(route('productos.store'), $datos);

        $response->assertSessionHasErrors('codigo');
    }

    public function test_crear_producto_falla_con_codigo_duplicado(): void
    {
        Producto::factory()->create(['codigo' => 'MED-DUPL']);

        $this->actingAs($this->admin);
        $datos = $this->datosProductoValidos();
        $datos['codigo'] = 'MED-DUPL';

        $response = $this->post(route('productos.store'), $datos);

        $response->assertSessionHasErrors('codigo');
    }

    public function test_crear_producto_falla_con_nombre_vacio(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosProductoValidos();
        $datos['nombre'] = '';

        $response = $this->post(route('productos.store'), $datos);

        $response->assertSessionHasErrors('nombre');
    }

    public function test_crear_producto_falla_con_categoria_inexistente(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosProductoValidos();
        $datos['categoria_id'] = 99999;

        $response = $this->post(route('productos.store'), $datos);

        $response->assertSessionHasErrors('categoria_id');
    }

    public function test_crear_producto_falla_con_precio_venta_negativo(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosProductoValidos();
        $datos['precio_venta'] = -1;

        $response = $this->post(route('productos.store'), $datos);

        $response->assertSessionHasErrors('precio_venta');
    }

    public function test_crear_producto_falla_con_precio_venta_no_numerico(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosProductoValidos();
        $datos['precio_venta'] = 'abc';

        $response = $this->post(route('productos.store'), $datos);

        $response->assertSessionHasErrors('precio_venta');
    }

    public function test_crear_producto_acepta_precio_venta_cero(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosProductoValidos();
        $datos['precio_venta'] = 0;

        $response = $this->post(route('productos.store'), $datos);

        $response->assertRedirect();
        $this->assertDatabaseHas('productos', ['codigo' => $datos['codigo'], 'precio_venta' => 0]);
    }

    public function test_crear_producto_falla_con_stock_minimo_negativo(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosProductoValidos();
        $datos['stock_minimo'] = -5;

        $response = $this->post(route('productos.store'), $datos);

        $response->assertSessionHasErrors('stock_minimo');
    }

    public function test_crear_producto_acepta_stock_minimo_cero(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosProductoValidos();
        $datos['stock_minimo'] = 0;
        $datos['codigo'] = 'MED-SMIN0';

        $response = $this->post(route('productos.store'), $datos);

        $response->assertRedirect();
        $this->assertDatabaseHas('productos', ['codigo' => 'MED-SMIN0', 'stock_minimo' => 0]);
    }

    public function test_crear_producto_falla_sin_unidad_de_medida(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosProductoValidos();
        $datos['unidad_medida'] = '';

        $response = $this->post(route('productos.store'), $datos);

        $response->assertSessionHasErrors('unidad_medida');
    }

    // -------------------------------------------------------------------------
    // Actualizar
    // -------------------------------------------------------------------------

    public function test_admin_puede_actualizar_producto(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs($this->admin);

        $response = $this->put(route('productos.update', $producto), [
            'codigo'        => $producto->codigo,
            'nombre'        => 'Nombre Actualizado',
            'categoria_id'  => $producto->categoria_id,
            'unidad_medida' => 'cápsula',
            'precio_venta'  => 12.00,
            'stock_minimo'  => 5,
            'activo'        => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('productos', [
            'id'     => $producto->id,
            'nombre' => 'Nombre Actualizado',
        ]);
    }

    public function test_actualizar_producto_permite_mismo_codigo_del_propio_registro(): void
    {
        $producto = Producto::factory()->create(['codigo' => 'MED-SAME']);

        $this->actingAs($this->admin);

        $response = $this->put(route('productos.update', $producto), [
            'codigo'        => 'MED-SAME',
            'nombre'        => $producto->nombre,
            'categoria_id'  => $producto->categoria_id,
            'unidad_medida' => $producto->unidad_medida,
            'precio_venta'  => 8.00,
            'stock_minimo'  => $producto->stock_minimo,
            'activo'        => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'precio_venta' => 8.00]);
    }

    public function test_actualizar_producto_falla_con_codigo_duplicado_de_otro_registro(): void
    {
        Producto::factory()->create(['codigo' => 'MED-OTRO']);
        $producto = Producto::factory()->create(['codigo' => 'MED-ESTE']);

        $this->actingAs($this->admin);

        $response = $this->put(route('productos.update', $producto), [
            'codigo'        => 'MED-OTRO',
            'nombre'        => $producto->nombre,
            'categoria_id'  => $producto->categoria_id,
            'unidad_medida' => $producto->unidad_medida,
            'precio_venta'  => 5.00,
            'stock_minimo'  => 10,
            'activo'        => true,
        ]);

        $response->assertSessionHasErrors('codigo');
    }

    // -------------------------------------------------------------------------
    // Eliminar
    // -------------------------------------------------------------------------

    public function test_admin_puede_eliminar_producto(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs($this->admin);

        $response = $this->delete(route('productos.destroy', $producto));

        $response->assertRedirect();
        $this->assertDatabaseMissing('productos', ['id' => $producto->id]);
    }

    // -------------------------------------------------------------------------
    // RBAC — Vendedor no puede crear / actualizar / eliminar
    // -------------------------------------------------------------------------

    public function test_vendedor_no_puede_crear_producto(): void
    {
        $this->actingAs($this->vendedor);
        $datos = $this->datosProductoValidos();

        $response = $this->post(route('productos.store'), $datos);

        $response->assertStatus(403);
    }

    public function test_vendedor_no_puede_actualizar_producto(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs($this->vendedor);

        $response = $this->put(route('productos.update', $producto), [
            'codigo'        => $producto->codigo,
            'nombre'        => 'Cambio no permitido',
            'categoria_id'  => $producto->categoria_id,
            'unidad_medida' => $producto->unidad_medida,
            'precio_venta'  => 1.00,
            'stock_minimo'  => 1,
            'activo'        => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_vendedor_no_puede_eliminar_producto(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs($this->vendedor);

        $response = $this->delete(route('productos.destroy', $producto));

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Usuario no autenticado
    // -------------------------------------------------------------------------

    public function test_usuario_no_autenticado_es_redirigido_desde_productos(): void
    {
        $response = $this->get(route('productos.index'));

        $response->assertRedirect(route('login'));
    }
}
