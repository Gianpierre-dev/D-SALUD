<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Categoria;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas HTTP del módulo Categorías.
 *
 * Cubre: CRUD completo (índice, crear, actualizar, eliminar),
 * validaciones de formulario y control de acceso por rol (RBAC).
 */
class CategoriaTest extends TestCase
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

    public function test_admin_puede_ver_el_indice_de_categorias(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('categorias.index'));

        $response->assertStatus(200);
    }

    public function test_vendedor_puede_ver_el_indice_de_categorias(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->get(route('categorias.index'));

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Crear
    // -------------------------------------------------------------------------

    public function test_admin_puede_crear_categoria(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('categorias.store'), [
            'nombre'      => 'Analgésicos',
            'descripcion' => 'Medicamentos para el dolor',
            'activo'      => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categorias', ['nombre' => 'Analgésicos']);
    }

    public function test_admin_puede_crear_categoria_sin_descripcion(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('categorias.store'), [
            'nombre' => 'Antibióticos',
            'activo' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categorias', ['nombre' => 'Antibióticos']);
    }

    // -------------------------------------------------------------------------
    // Validaciones — store
    // -------------------------------------------------------------------------

    public function test_crear_categoria_falla_con_nombre_vacio(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('categorias.store'), [
            'nombre' => '',
            'activo' => true,
        ]);

        $response->assertSessionHasErrors('nombre');
    }

    public function test_crear_categoria_falla_con_nombre_duplicado(): void
    {
        Categoria::factory()->create(['nombre' => 'Vitaminas y Suplementos']);

        $this->actingAs($this->admin);

        $response = $this->post(route('categorias.store'), [
            'nombre' => 'Vitaminas y Suplementos',
            'activo' => true,
        ]);

        $response->assertSessionHasErrors('nombre');
    }

    // -------------------------------------------------------------------------
    // Actualizar
    // -------------------------------------------------------------------------

    public function test_admin_puede_actualizar_categoria(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Antiácidos']);

        $this->actingAs($this->admin);

        $response = $this->put(route('categorias.update', $categoria), [
            'nombre'      => 'Antiácidos Mejorado',
            'descripcion' => 'Descripción actualizada',
            'activo'      => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categorias', ['id' => $categoria->id, 'nombre' => 'Antiácidos Mejorado']);
    }

    public function test_actualizar_categoria_permite_mismo_nombre_del_propio_registro(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Dermatológicos']);

        $this->actingAs($this->admin);

        $response = $this->put(route('categorias.update', $categoria), [
            'nombre' => 'Dermatológicos',
            'activo' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categorias', ['id' => $categoria->id, 'activo' => false]);
    }

    public function test_actualizar_categoria_falla_con_nombre_duplicado_de_otro_registro(): void
    {
        Categoria::factory()->create(['nombre' => 'Antiparasitarios']);
        $categoria = Categoria::factory()->create(['nombre' => 'Antihistamínicos']);

        $this->actingAs($this->admin);

        $response = $this->put(route('categorias.update', $categoria), [
            'nombre' => 'Antiparasitarios',
            'activo' => true,
        ]);

        $response->assertSessionHasErrors('nombre');
    }

    // -------------------------------------------------------------------------
    // Eliminar
    // -------------------------------------------------------------------------

    public function test_admin_puede_eliminar_categoria(): void
    {
        $categoria = Categoria::factory()->create();

        $this->actingAs($this->admin);

        $response = $this->delete(route('categorias.destroy', $categoria));

        $response->assertRedirect();
        $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
    }

    // -------------------------------------------------------------------------
    // RBAC — Vendedor no puede crear / actualizar / eliminar
    // -------------------------------------------------------------------------

    public function test_vendedor_no_puede_crear_categoria(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->post(route('categorias.store'), [
            'nombre' => 'Antidiabéticos',
            'activo' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_vendedor_no_puede_actualizar_categoria(): void
    {
        $categoria = Categoria::factory()->create();

        $this->actingAs($this->vendedor);

        $response = $this->put(route('categorias.update', $categoria), [
            'nombre' => 'Nombre cambiado',
            'activo' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_vendedor_no_puede_eliminar_categoria(): void
    {
        $categoria = Categoria::factory()->create();

        $this->actingAs($this->vendedor);

        $response = $this->delete(route('categorias.destroy', $categoria));

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Usuario no autenticado es redirigido al login
    // -------------------------------------------------------------------------

    public function test_usuario_no_autenticado_es_redirigido_desde_categorias(): void
    {
        $response = $this->get(route('categorias.index'));

        $response->assertRedirect(route('login'));
    }
}
