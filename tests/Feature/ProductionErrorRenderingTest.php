<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Resguarda el render de excepciones en producción (bootstrap/app.php).
 *
 * El closure de render solo actúa cuando el entorno es 'production', por eso
 * estas pruebas fuerzan ese entorno: en 'testing' el closure retorna null y el
 * bug no se manifestaría. Garantiza que las excepciones con manejo nativo
 * (autenticación, validación) NO se conviertan en un 500 falso.
 */
class ProductionErrorRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        // Forzar entorno de producción para activar el closure de render.
        $this->app['env'] = 'production';
    }

    public function test_invitado_en_produccion_es_redirigido_a_login_no_500(): void
    {
        // Antes del fix, AuthenticationException caía en el closure de render
        // y se convertía en un 500 (no expone getStatusCode). Debe redirigir.
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_ruta_protegida_de_caja_tambien_redirige_no_500(): void
    {
        // Segunda ruta protegida para confirmar que el manejo es general y no
        // específico del dashboard.
        $response = $this->get('/ventas');

        $response->assertRedirect(route('login'));
    }
}
