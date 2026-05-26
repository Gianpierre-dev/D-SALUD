<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Services\AuditoriaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas HTTP de los módulos de administración:
 * Auditoría, Empresa (configuración), Dashboard y Reportes.
 *
 * Cubre: acceso por rol, validaciones y descarga de Excel.
 */
class AdminModulosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // Empresa singleton necesaria para EmpresaService::obtener()
        Empresa::firstOrCreate(
            ['id' => 1],
            [
                'razon_social' => "Botica D'Salud S.A.C.",
                'ruc'          => '20600000001',
                'direccion'    => 'Av. Principal 123, Lima',
                'telefono'     => '01-0000000',
            ],
        );

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Rol::ADMINISTRADOR->value);

        $this->vendedor = User::factory()->create();
        $this->vendedor->assignRole(Rol::VENDEDOR->value);
    }

    // =========================================================================
    // AUDITORÍA
    // =========================================================================

    public function test_admin_puede_ver_la_pagina_de_auditoria(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('auditoria.index'));

        $response->assertStatus(200);
    }

    public function test_vendedor_recibe_403_al_intentar_ver_auditoria(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->get(route('auditoria.index'));

        $response->assertStatus(403);
    }

    public function test_crear_una_entidad_registra_una_fila_en_registro_auditoria(): void
    {
        // Usamos AuditoriaService directamente para simular lo que ocurre
        // cuando cualquier service (usuarios, roles, etc.) registra una acción.
        $this->actingAs($this->admin);

        /** @var AuditoriaService $auditoriaService */
        $auditoriaService = app(AuditoriaService::class);

        $auditoriaService->registrar('usuarios', 'crear', 'Usuario #99: Test de auditoría');

        $this->assertDatabaseHas('registro_auditoria', [
            'user_id' => $this->admin->id,
            'modulo'  => 'usuarios',
            'accion'  => 'crear',
        ]);
    }

    public function test_crear_usuario_genera_registro_en_auditoria(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('usuarios.store'), [
            'name'                  => 'Usuario Auditado',
            'email'                 => 'auditado@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'rol'                   => Rol::VENDEDOR->value,
        ]);

        $this->assertDatabaseHas('registro_auditoria', [
            'user_id' => $this->admin->id,
            'modulo'  => 'usuarios',
            'accion'  => 'crear',
        ]);
    }

    // =========================================================================
    // EMPRESA (CONFIGURACIÓN)
    // =========================================================================

    public function test_admin_puede_ver_la_pagina_de_configuracion_empresa(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('configuracion.edit'));

        $response->assertStatus(200);
    }

    public function test_admin_puede_actualizar_los_datos_de_empresa(): void
    {
        $this->actingAs($this->admin);

        $response = $this->put(route('configuracion.update'), [
            'razon_social' => 'Farmacia Nueva S.A.C.',
            'ruc'          => '20712345678',
            'direccion'    => 'Jr. Los Pinos 456, Lima',
            'telefono'     => '01-1234567',
        ]);

        $response->assertRedirect(route('configuracion.edit'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('empresa', [
            'razon_social' => 'Farmacia Nueva S.A.C.',
            'ruc'          => '20712345678',
        ]);
    }

    // -------------------------------------------------------------------------
    // Validaciones empresa: ruc debe tener 11 dígitos
    // -------------------------------------------------------------------------

    public function test_no_se_puede_actualizar_empresa_con_ruc_de_menos_de_11_digitos(): void
    {
        $this->actingAs($this->admin);

        $response = $this->put(route('configuracion.update'), [
            'razon_social' => 'Farmacia S.A.C.',
            'ruc'          => '2071234',   // solo 7 dígitos
            'direccion'    => 'Lima',
            'telefono'     => null,
        ]);

        $response->assertSessionHasErrors('ruc');
    }

    public function test_no_se_puede_actualizar_empresa_con_ruc_de_mas_de_11_digitos(): void
    {
        $this->actingAs($this->admin);

        $response = $this->put(route('configuracion.update'), [
            'razon_social' => 'Farmacia S.A.C.',
            'ruc'          => '207123456789',  // 12 dígitos
            'direccion'    => 'Lima',
            'telefono'     => null,
        ]);

        $response->assertSessionHasErrors('ruc');
    }

    // -------------------------------------------------------------------------
    // Validaciones empresa: razon_social requerida
    // -------------------------------------------------------------------------

    public function test_no_se_puede_actualizar_empresa_sin_razon_social(): void
    {
        $this->actingAs($this->admin);

        $response = $this->put(route('configuracion.update'), [
            'razon_social' => '',
            'ruc'          => '20712345678',
            'direccion'    => 'Lima',
            'telefono'     => null,
        ]);

        $response->assertSessionHasErrors('razon_social');
    }

    // -------------------------------------------------------------------------
    // RBAC: vendedor no puede acceder a configuracion.*
    // -------------------------------------------------------------------------

    public function test_vendedor_recibe_403_al_intentar_ver_configuracion(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->get(route('configuracion.edit'));

        $response->assertStatus(403);
    }

    public function test_vendedor_recibe_403_al_intentar_actualizar_configuracion(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->put(route('configuracion.update'), [
            'razon_social' => 'Intento vendedor',
            'ruc'          => '20712345678',
        ]);

        $response->assertStatus(403);
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function test_admin_puede_ver_el_dashboard(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
    }

    public function test_vendedor_puede_ver_el_dashboard(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
    }

    // =========================================================================
    // REPORTES
    // =========================================================================

    public function test_admin_puede_descargar_reporte_excel_de_stock_bajo(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('reportes.lotesStockBajo'));

        $response->assertStatus(200);

        // Verificar que la respuesta es una descarga (archivo binario)
        $contentType = $response->headers->get('Content-Type');
        $this->assertNotNull($contentType);
        $this->assertStringContainsString(
            'spreadsheetml',
            $contentType,
            "Se esperaba Content-Type de Excel, se recibió: {$contentType}",
        );
    }

    public function test_admin_puede_descargar_reporte_excel_de_productos_por_vencer(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('reportes.productosPorVencer'));

        $response->assertStatus(200);
    }

    public function test_admin_puede_ver_la_pagina_principal_de_reportes(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('reportes.index'));

        $response->assertStatus(200);
    }

    public function test_vendedor_recibe_403_al_intentar_acceder_a_reportes(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->get(route('reportes.index'));

        $response->assertStatus(403);
    }

    public function test_vendedor_recibe_403_al_intentar_descargar_reporte_de_stock_bajo(): void
    {
        $this->actingAs($this->vendedor);

        $response = $this->get(route('reportes.lotesStockBajo'));

        $response->assertStatus(403);
    }
}
