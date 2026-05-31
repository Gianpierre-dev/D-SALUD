<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empresa;
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

        // La vista de boleta requiere la configuración singleton de empresa.
        Empresa::create([
            'razon_social' => "Botica D'Salud S.A.C.",
            'ruc' => '20600000001',
            'direccion' => 'Lima, Perú',
        ]);

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
    // Descarga del PDF (DomPDF)
    // -------------------------------------------------------------------------

    public function test_admin_puede_descargar_pdf_de_cualquier_boleta(): void
    {
        $vendedor = User::factory()->create();
        $vendedor->assignRole('Vendedor');

        $venta = $this->crearVentaParaUsuario($vendedor);

        $admin = User::factory()->create();
        $admin->assignRole('Administrador');

        $this->actingAs($admin);
        $response = $this->get(route('ventas.boleta.pdf', $venta));

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_vendedor_no_puede_descargar_pdf_de_venta_ajena(): void
    {
        $vendedor1 = User::factory()->create();
        $vendedor1->assignRole('Vendedor');

        $vendedor2 = User::factory()->create();
        $vendedor2->assignRole('Vendedor');

        $venta = $this->crearVentaParaUsuario($vendedor1);

        $this->actingAs($vendedor2);
        $response = $this->get(route('ventas.boleta.pdf', $venta));

        $response->assertStatus(403);
    }

    public function test_vendedor_puede_descargar_pdf_de_su_propia_venta(): void
    {
        $vendedor = User::factory()->create();
        $vendedor->assignRole('Vendedor');

        $venta = $this->crearVentaParaUsuario($vendedor);

        $this->actingAs($vendedor);
        $response = $this->get(route('ventas.boleta.pdf', $venta));

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    // -------------------------------------------------------------------------
    // Registro público deshabilitado
    // -------------------------------------------------------------------------

    public function test_la_ruta_register_no_existe(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Filtro de historial por cliente_id
    // -------------------------------------------------------------------------

    public function test_admin_filtra_historial_por_cliente_id(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrador');

        $clienteA = Cliente::factory()->create();
        $clienteB = Cliente::factory()->create();

        // Venta de A
        $this->actingAs($admin);
        $producto = Producto::factory()->create(['precio_venta' => 10.00, 'activo' => true]);
        Lote::factory()->vigente()->conStock(50)->create(['producto_id' => $producto->id]);
        $ventaA = $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $admin->id,
            $clienteA->id,
        );

        // Venta de B
        Lote::factory()->vigente()->conStock(50)->create(['producto_id' => $producto->id]);
        $ventaB = $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $admin->id,
            $clienteB->id,
        );

        // Venta sin cliente (no debe aparecer en el filtro)
        Lote::factory()->vigente()->conStock(50)->create(['producto_id' => $producto->id]);
        $ventaSinCliente = $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $admin->id,
        );

        // Filtrando por clienteA solo debe venir ventaA.
        $response = $this->get(route('ventas.index', ['cliente_id' => $clienteA->id]));
        $response->assertStatus(200);

        $ids = collect($response->viewData('page')['props']['ventas']['data'])->pluck('id')->all();
        $this->assertContains($ventaA->id, $ids);
        $this->assertNotContains($ventaB->id, $ids);
        $this->assertNotContains($ventaSinCliente->id, $ids);
    }

    public function test_vendedor_filtra_por_cliente_solo_sobre_sus_propias_ventas(): void
    {
        $vendedor = User::factory()->create();
        $vendedor->assignRole('Vendedor');

        $otroVendedor = User::factory()->create();
        $otroVendedor->assignRole('Vendedor');

        $cliente = Cliente::factory()->create();

        $producto = Producto::factory()->create(['precio_venta' => 10.00, 'activo' => true]);

        // Venta del vendedor (debe aparecer)
        $this->actingAs($vendedor);
        Lote::factory()->vigente()->conStock(20)->create(['producto_id' => $producto->id]);
        $ventaPropia = $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $vendedor->id,
            $cliente->id,
        );

        // Venta de otro vendedor al MISMO cliente (NO debe aparecer)
        $this->actingAs($otroVendedor);
        Lote::factory()->vigente()->conStock(20)->create(['producto_id' => $producto->id]);
        $ventaAjena = $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $otroVendedor->id,
            $cliente->id,
        );

        // El vendedor consulta con filtro cliente_id
        $this->actingAs($vendedor);
        $response = $this->get(route('ventas.index', ['cliente_id' => $cliente->id]));
        $response->assertStatus(200);

        $ids = collect($response->viewData('page')['props']['ventas']['data'])->pluck('id')->all();
        $this->assertContains($ventaPropia->id, $ids);
        $this->assertNotContains($ventaAjena->id, $ids);
    }
}
