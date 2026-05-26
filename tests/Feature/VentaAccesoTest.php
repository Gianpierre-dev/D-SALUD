<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\VentaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de control de acceso por rol en el módulo de Ventas.
 *
 * Cubre: Vendedor no puede ver boleta ajena, Vendedor sí puede ver su propia
 * boleta, y la ruta /register no existe (sistema interno).
 */
class VentaAccesoTest extends TestCase
{
    use RefreshDatabase;

    private VentaService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        /** @var VentaService $servicio */
        $this->servicio = app(VentaService::class);
    }

    /**
     * Registra una venta para el usuario indicado y devuelve el modelo Venta.
     */
    private function crearVentaParaUsuario(User $usuario): Venta
    {
        $this->actingAs($usuario);

        $producto = Producto::factory()->create(['precio_venta' => 10.00, 'activo' => true]);
        Lote::factory()->vigente()->conStock(50)->create(['producto_id' => $producto->id]);

        return $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $usuario->id
        );
    }

    // -------------------------------------------------------------------------
    // Control de acceso: boleta ajena
    // -------------------------------------------------------------------------

    public function test_vendedor_recibe_403_al_pedir_boleta_de_venta_de_otro_usuario(): void
    {
        $vendedor1 = User::factory()->create();
        $vendedor1->assignRole('Vendedor');

        $vendedor2 = User::factory()->create();
        $vendedor2->assignRole('Vendedor');

        // La venta pertenece a vendedor1
        $venta = $this->crearVentaParaUsuario($vendedor1);

        // vendedor2 intenta acceder a la boleta de vendedor1
        $this->actingAs($vendedor2);
        $response = $this->get(route('ventas.boleta', $venta));

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Control de acceso: boleta propia
    // -------------------------------------------------------------------------

    public function test_vendedor_puede_ver_la_boleta_de_su_propia_venta(): void
    {
        $vendedor = User::factory()->create();
        $vendedor->assignRole('Vendedor');

        $venta = $this->crearVentaParaUsuario($vendedor);

        // El mismo vendedor accede a su propia boleta
        $this->actingAs($vendedor);
        $response = $this->get(route('ventas.boleta', $venta));

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Registro público deshabilitado
    // -------------------------------------------------------------------------

    public function test_la_ruta_register_no_existe(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(404);
    }
}
