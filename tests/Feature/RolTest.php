<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas HTTP del módulo de Roles.
 *
 * Cubre: creación con permisos, validación de nombre único,
 * protección de roles del sistema y control de acceso por rol.
 */
class RolTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // Empresa singleton requerida por AuditoriaService en operaciones de roles
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
    // Creación de rol con permisos
    // -------------------------------------------------------------------------

    public function test_admin_puede_crear_rol_con_permisos_y_quedan_asignados(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('roles.store'), [
            'name'        => 'Supervisor',
            'permissions' => ['categorias.read', 'productos.read'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roles', ['name' => 'Supervisor']);

        $rol = Role::where('name', 'Supervisor')->firstOrFail();
        $this->assertTrue($rol->hasPermissionTo('categorias.read'));
        $this->assertTrue($rol->hasPermissionTo('productos.read'));
    }

    // -------------------------------------------------------------------------
    // Validaciones: nombre único
    // -------------------------------------------------------------------------

    public function test_no_se_puede_crear_rol_con_nombre_ya_existente(): void
    {
        $this->actingAs($this->admin);

        // El rol 'Administrador' ya existe por el seeder
        $response = $this->post(route('roles.store'), [
            'name'        => Rol::ADMINISTRADOR->value,
            'permissions' => [],
        ]);

        $response->assertSessionHasErrors('name');
    }

    // -------------------------------------------------------------------------
    // Protección de roles del sistema
    // -------------------------------------------------------------------------

    public function test_no_se_puede_eliminar_el_rol_administrador(): void
    {
        $this->actingAs($this->admin);

        $rolAdmin = Role::where('name', Rol::ADMINISTRADOR->value)->firstOrFail();

        $response = $this->delete(route('roles.destroy', $rolAdmin));

        // Debe redirigir con mensaje de error en sesión
        $response->assertRedirect();
        $response->assertSessionHas('error');

        // El rol sigue existiendo en la base de datos
        $this->assertDatabaseHas('roles', ['name' => Rol::ADMINISTRADOR->value]);
    }

    public function test_no_se_puede_eliminar_el_rol_vendedor(): void
    {
        $this->actingAs($this->admin);

        $rolVendedor = Role::where('name', Rol::VENDEDOR->value)->firstOrFail();

        $response = $this->delete(route('roles.destroy', $rolVendedor));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['name' => Rol::VENDEDOR->value]);
    }

    // -------------------------------------------------------------------------
    // Eliminación de rol no protegido
    // -------------------------------------------------------------------------

    public function test_admin_puede_eliminar_un_rol_no_protegido(): void
    {
        $this->actingAs($this->admin);

        // Crear un rol personalizado (no protegido)
        $rolPersonalizado = Role::create(['name' => 'RolTemporal', 'guard_name' => 'web']);

        $response = $this->delete(route('roles.destroy', $rolPersonalizado));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');

        // El rol debe haber sido eliminado
        $this->assertDatabaseMissing('roles', ['name' => 'RolTemporal']);
    }

    // -------------------------------------------------------------------------
    // RBAC: vendedor no puede acceder a roles.*
    // -------------------------------------------------------------------------

    public function test_vendedor_recibe_403_al_intentar_ver_lista_de_roles(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->get(route('roles.index'));

        $response->assertStatus(403);
    }

    public function test_vendedor_recibe_403_al_intentar_crear_un_rol(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->post(route('roles.store'), [
            'name'        => 'RolIntruso',
            'permissions' => [],
        ]);

        $response->assertStatus(403);
    }

    public function test_vendedor_recibe_403_al_intentar_eliminar_un_rol(): void
    {
        $this->actingAs($this->vendedor);

        $rolExistente = Role::where('name', Rol::ADMINISTRADOR->value)->firstOrFail();

        $response = $this->delete(route('roles.destroy', $rolExistente));

        $response->assertStatus(403);
    }
}
