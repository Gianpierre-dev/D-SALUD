<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas HTTP del módulo Proveedores.
 *
 * Cubre: CRUD completo (índice, crear, actualizar, eliminar),
 * validaciones de formulario (RUC) y control de acceso por rol (RBAC).
 * El Vendedor no tiene ningún permiso sobre proveedores → 403 en index también.
 */
class ProveedorTest extends TestCase
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

    // -------------------------------------------------------------------------
    // Índice
    // -------------------------------------------------------------------------

    public function test_admin_puede_ver_el_indice_de_proveedores(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('proveedores.index'));

        $response->assertStatus(200);
    }

    public function test_vendedor_no_puede_ver_el_indice_de_proveedores(): void
    {
        // El Vendedor no tiene permiso proveedores.read
        $this->actingAs($this->vendedor);

        $response = $this->get(route('proveedores.index'));

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Crear
    // -------------------------------------------------------------------------

    public function test_admin_puede_crear_proveedor(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('proveedores.store'), [
            'ruc'          => '20123456789',
            'razon_social' => 'Distribuidora Farma SAC',
            'contacto'     => 'Juan Pérez',
            'telefono'     => '987654321',
            'email'        => 'contacto@farma.com',
            'direccion'    => 'Av. Lima 123',
            'activo'       => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('proveedores', ['ruc' => '20123456789']);
    }

    public function test_admin_puede_crear_proveedor_con_campos_opcionales_nulos(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('proveedores.store'), [
            'ruc'          => '20987654321',
            'razon_social' => 'Medifarma SAC',
            'activo'       => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('proveedores', ['ruc' => '20987654321']);
    }

    // -------------------------------------------------------------------------
    // Validaciones — RUC
    // -------------------------------------------------------------------------

    public function test_crear_proveedor_falla_con_ruc_vacio(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('proveedores.store'), [
            'ruc'          => '',
            'razon_social' => 'Empresa Sin RUC',
        ]);

        $response->assertSessionHasErrors('ruc');
    }

    public function test_crear_proveedor_falla_con_ruc_de_menos_de_11_digitos(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('proveedores.store'), [
            'ruc'          => '2012345678',   // 10 dígitos
            'razon_social' => 'Empresa Corta',
        ]);

        $response->assertSessionHasErrors('ruc');
    }

    public function test_crear_proveedor_falla_con_ruc_de_mas_de_11_digitos(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('proveedores.store'), [
            'ruc'          => '201234567890',  // 12 dígitos
            'razon_social' => 'Empresa Larga',
        ]);

        $response->assertSessionHasErrors('ruc');
    }

    public function test_crear_proveedor_falla_con_ruc_no_numerico(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('proveedores.store'), [
            'ruc'          => '2012345678A',   // contiene letra
            'razon_social' => 'Empresa Invalida',
        ]);

        $response->assertSessionHasErrors('ruc');
    }

    public function test_crear_proveedor_falla_con_ruc_duplicado(): void
    {
        Proveedor::factory()->create(['ruc' => '20111222333']);

        $this->actingAs($this->admin);

        $response = $this->post(route('proveedores.store'), [
            'ruc'          => '20111222333',
            'razon_social' => 'Otro Proveedor',
        ]);

        $response->assertSessionHasErrors('ruc');
    }

    public function test_crear_proveedor_falla_con_razon_social_vacia(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('proveedores.store'), [
            'ruc'          => '20123456781',
            'razon_social' => '',
        ]);

        $response->assertSessionHasErrors('razon_social');
    }

    // -------------------------------------------------------------------------
    // Actualizar
    // -------------------------------------------------------------------------

    public function test_admin_puede_actualizar_proveedor(): void
    {
        $proveedor = Proveedor::factory()->create();

        $this->actingAs($this->admin);

        $response = $this->put(route('proveedores.update', $proveedor), [
            'ruc'          => $proveedor->ruc,
            'razon_social' => 'Razón Social Actualizada',
            'activo'       => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('proveedores', [
            'id'           => $proveedor->id,
            'razon_social' => 'Razón Social Actualizada',
        ]);
    }

    public function test_actualizar_proveedor_permite_mismo_ruc_del_propio_registro(): void
    {
        $proveedor = Proveedor::factory()->create(['ruc' => '20444555666']);

        $this->actingAs($this->admin);

        $response = $this->put(route('proveedores.update', $proveedor), [
            'ruc'          => '20444555666',
            'razon_social' => $proveedor->razon_social,
            'activo'       => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id, 'activo' => false]);
    }

    public function test_actualizar_proveedor_falla_con_ruc_duplicado_de_otro_registro(): void
    {
        Proveedor::factory()->create(['ruc' => '20777888999']);
        $proveedor = Proveedor::factory()->create();

        $this->actingAs($this->admin);

        $response = $this->put(route('proveedores.update', $proveedor), [
            'ruc'          => '20777888999',
            'razon_social' => 'Nuevo nombre',
        ]);

        $response->assertSessionHasErrors('ruc');
    }

    // -------------------------------------------------------------------------
    // Eliminar
    // -------------------------------------------------------------------------

    public function test_admin_puede_eliminar_proveedor(): void
    {
        $proveedor = Proveedor::factory()->create();

        $this->actingAs($this->admin);

        $response = $this->delete(route('proveedores.destroy', $proveedor));

        $response->assertRedirect();
        $this->assertDatabaseMissing('proveedores', ['id' => $proveedor->id]);
    }

    // -------------------------------------------------------------------------
    // RBAC — Vendedor no puede crear / actualizar / eliminar
    // -------------------------------------------------------------------------

    public function test_vendedor_no_puede_crear_proveedor(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->post(route('proveedores.store'), [
            'ruc'          => '20321456987',
            'razon_social' => 'Proveedor No Permitido',
        ]);

        $response->assertStatus(403);
    }

    public function test_vendedor_no_puede_actualizar_proveedor(): void
    {
        $proveedor = Proveedor::factory()->create();

        $this->actingAs($this->vendedor);

        $response = $this->put(route('proveedores.update', $proveedor), [
            'ruc'          => $proveedor->ruc,
            'razon_social' => 'Cambio no permitido',
        ]);

        $response->assertStatus(403);
    }

    public function test_vendedor_no_puede_eliminar_proveedor(): void
    {
        $proveedor = Proveedor::factory()->create();

        $this->actingAs($this->vendedor);

        $response = $this->delete(route('proveedores.destroy', $proveedor));

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Usuario no autenticado
    // -------------------------------------------------------------------------

    public function test_usuario_no_autenticado_es_redirigido_desde_proveedores(): void
    {
        $response = $this->get(route('proveedores.index'));

        $response->assertRedirect(route('login'));
    }
}
