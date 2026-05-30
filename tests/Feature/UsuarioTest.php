<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas HTTP del módulo de Usuarios.
 *
 * Cubre: creación, validación, actualización y control de acceso por rol.
 */
class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // Empresa singleton requerida por EmpresaService::obtener()
        Empresa::firstOrCreate(
            ['id' => 1],
            [
                'razon_social' => "Botica D'Salud S.A.C.",
                'ruc'          => '20600000001',
                'direccion'    => 'Lima',
                'telefono'     => '01-0000000',
            ],
        );

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Rol::ADMINISTRADOR->value);

        $this->vendedor = User::factory()->create();
        $this->vendedor->assignRole(Rol::VENDEDOR->value);
    }

    // -------------------------------------------------------------------------
    // Creación de usuario
    // -------------------------------------------------------------------------

    public function test_admin_puede_crear_usuario_con_rol_y_se_persiste_en_base_de_datos(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('usuarios.store'), [
            'name'                  => 'Juan Pérez',
            'email'                 => 'juan@example.com',
            'password'              => 'NuevaClave123!',
            'password_confirmation' => 'NuevaClave123!',
            'rol'                   => Rol::VENDEDOR->value,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name'  => 'Juan Pérez',
            'email' => 'juan@example.com',
        ]);

        $usuario = User::where('email', 'juan@example.com')->firstOrFail();
        $this->assertTrue($usuario->hasRole(Rol::VENDEDOR->value));
    }

    // -------------------------------------------------------------------------
    // Validaciones: email único
    // -------------------------------------------------------------------------

    public function test_no_se_puede_crear_usuario_con_email_ya_registrado(): void
    {
        $this->actingAs($this->admin);

        // Primero crear un usuario con ese email
        User::factory()->create(['email' => 'duplicado@example.com']);

        $response = $this->post(route('usuarios.store'), [
            'name'                  => 'Otro Usuario',
            'email'                 => 'duplicado@example.com',
            'password'              => 'NuevaClave123!',
            'password_confirmation' => 'NuevaClave123!',
            'rol'                   => Rol::VENDEDOR->value,
        ]);

        $response->assertSessionHasErrors('email');
    }

    // -------------------------------------------------------------------------
    // Validaciones: password debe cumplir política NIST (min 10 + complejidad)
    // -------------------------------------------------------------------------

    public function test_no_se_puede_crear_usuario_con_password_que_no_cumple_politica_nist(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('usuarios.store'), [
            'name'                  => 'Nuevo',
            'email'                 => 'nuevo@example.com',
            'password'              => 'corto',
            'password_confirmation' => 'corto',
            'rol'                   => Rol::VENDEDOR->value,
        ]);

        $response->assertSessionHasErrors('password');
    }

    // -------------------------------------------------------------------------
    // Validaciones: password confirmado
    // -------------------------------------------------------------------------

    public function test_no_se_puede_crear_usuario_si_password_y_confirmacion_no_coinciden(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('usuarios.store'), [
            'name'                  => 'Nuevo',
            'email'                 => 'nuevo@example.com',
            'password'              => 'NuevaClave123!',
            'password_confirmation' => 'OtraClave123!',
            'rol'                   => Rol::VENDEDOR->value,
        ]);

        $response->assertSessionHasErrors('password');
    }

    // -------------------------------------------------------------------------
    // Validaciones: rol debe existir
    // -------------------------------------------------------------------------

    public function test_no_se_puede_crear_usuario_con_rol_inexistente(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('usuarios.store'), [
            'name'                  => 'Nuevo',
            'email'                 => 'nuevo@example.com',
            'password'              => 'NuevaClave123!',
            'password_confirmation' => 'NuevaClave123!',
            'rol'                   => 'rol_que_no_existe',
        ]);

        $response->assertSessionHasErrors('rol');
    }

    // -------------------------------------------------------------------------
    // Actualización de usuario
    // -------------------------------------------------------------------------

    public function test_admin_puede_actualizar_nombre_email_y_cambiar_rol_de_usuario(): void
    {
        $this->actingAs($this->admin);

        $usuario = User::factory()->create(['email' => 'original@example.com']);
        $usuario->assignRole(Rol::VENDEDOR->value);

        $response = $this->put(route('usuarios.update', $usuario), [
            'name'  => 'Nombre Actualizado',
            'email' => 'actualizado@example.com',
            'rol'   => Rol::ADMINISTRADOR->value,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id'    => $usuario->id,
            'name'  => 'Nombre Actualizado',
            'email' => 'actualizado@example.com',
        ]);

        $this->assertTrue($usuario->fresh()->hasRole(Rol::ADMINISTRADOR->value));
    }

    public function test_admin_puede_actualizar_usuario_sin_cambiar_el_password(): void
    {
        $this->actingAs($this->admin);

        $usuario = User::factory()->create(['email' => 'sin-pwd@example.com']);
        $usuario->assignRole(Rol::VENDEDOR->value);

        $passwordOriginal = $usuario->password;

        $response = $this->put(route('usuarios.update', $usuario), [
            'name'  => 'Sin Cambio Pwd',
            'email' => 'sin-pwd@example.com',
            'rol'   => Rol::VENDEDOR->value,
            // No se envía password
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // El password no debe haber cambiado
        $this->assertEquals($passwordOriginal, $usuario->fresh()->password);
    }

    // -------------------------------------------------------------------------
    // RBAC: vendedor no puede acceder a usuarios.*
    // -------------------------------------------------------------------------

    public function test_vendedor_recibe_403_al_intentar_ver_lista_de_usuarios(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->get(route('usuarios.index'));

        $response->assertStatus(403);
    }

    public function test_vendedor_recibe_403_al_intentar_crear_un_usuario(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->post(route('usuarios.store'), [
            'name'                  => 'Intruso',
            'email'                 => 'intruso@example.com',
            'password'              => 'NuevaClave123!',
            'password_confirmation' => 'NuevaClave123!',
            'rol'                   => Rol::VENDEDOR->value,
        ]);

        $response->assertStatus(403);
    }
}
