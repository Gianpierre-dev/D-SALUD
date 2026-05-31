<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Rol;
use App\Enums\TipoDocumento;
use App\Models\Cliente;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas HTTP del módulo de Clientes.
 *
 * Cubre: CRUD, validación condicional por tipo de documento (DNI 8 dígitos /
 * RUC 11 dígitos con prefijo válido), unicidad de numero_documento y RBAC.
 */
class ClienteTest extends TestCase
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

    public function test_admin_puede_crear_cliente_con_dni_valido(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('clientes.store'), [
            'tipo_documento'   => TipoDocumento::DNI->value,
            'numero_documento' => '12345678',
            'nombre'           => 'Juan Pérez',
            'telefono'         => '987654321',
            'email'            => 'juan@example.com',
            'direccion'        => 'Av. Lima 123',
            'activo'           => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clientes', [
            'numero_documento' => '12345678',
            'tipo_documento'   => 'DNI',
        ]);
    }

    public function test_admin_puede_crear_cliente_con_ruc_valido(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('clientes.store'), [
            'tipo_documento'   => TipoDocumento::RUC->value,
            'numero_documento' => '20712345678',
            'nombre'           => 'Botica San Marcos S.A.C.',
            'activo'           => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clientes', [
            'numero_documento' => '20712345678',
            'tipo_documento'   => 'RUC',
        ]);
    }

    public function test_no_se_acepta_dni_con_largo_distinto_a_8(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('clientes.store'), [
            'tipo_documento'   => TipoDocumento::DNI->value,
            'numero_documento' => '1234567',
            'nombre'           => 'Corto',
        ]);

        $response->assertSessionHasErrors('numero_documento');
    }

    public function test_no_se_acepta_ruc_con_prefijo_invalido(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('clientes.store'), [
            'tipo_documento'   => TipoDocumento::RUC->value,
            // Prefijo 99 no es válido: el regex exige 10/15/16/17/20.
            'numero_documento' => '99999999999',
            'nombre'           => 'Inválido',
        ]);

        $response->assertSessionHasErrors('numero_documento');
    }

    public function test_no_se_puede_crear_cliente_con_numero_de_documento_duplicado(): void
    {
        $this->actingAs($this->admin);

        Cliente::factory()->create(['numero_documento' => '12345678']);

        $response = $this->post(route('clientes.store'), [
            'tipo_documento'   => TipoDocumento::DNI->value,
            'numero_documento' => '12345678',
            'nombre'           => 'Duplicado',
        ]);

        $response->assertSessionHasErrors('numero_documento');
    }

    public function test_admin_puede_actualizar_cliente(): void
    {
        $this->actingAs($this->admin);

        $cliente = Cliente::factory()->create();

        $response = $this->put(route('clientes.update', $cliente), [
            'tipo_documento'   => $cliente->tipo_documento->value,
            'numero_documento' => $cliente->numero_documento,
            'nombre'           => 'Nombre Actualizado',
            'activo'           => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('Nombre Actualizado', $cliente->fresh()->nombre);
    }

    public function test_admin_puede_eliminar_cliente_sin_ventas(): void
    {
        $this->actingAs($this->admin);

        $cliente = Cliente::factory()->create();

        $response = $this->delete(route('clientes.destroy', $cliente));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertModelMissing($cliente);
    }

    public function test_vendedor_puede_listar_y_crear_pero_no_eliminar(): void
    {
        $this->actingAs($this->vendedor);

        // Listar: 200
        $this->get(route('clientes.index'))->assertStatus(200);

        // Crear: 302 (redirect tras success), no 403
        $crear = $this->post(route('clientes.store'), [
            'tipo_documento'   => TipoDocumento::DNI->value,
            'numero_documento' => '87654321',
            'nombre'           => 'Cliente POS',
        ]);
        $crear->assertRedirect();
        $crear->assertSessionHasNoErrors();

        // Eliminar: 403 (no tiene permiso clientes.delete)
        $cliente = Cliente::factory()->create();
        $this->delete(route('clientes.destroy', $cliente))->assertStatus(403);
    }
}
