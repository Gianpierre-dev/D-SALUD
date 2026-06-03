<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Rol;
use App\Enums\TipoDocumento;
use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Services\CajaService;
use App\Services\VentaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del Bloque 2: desglose de IGV y tipo de comprobante (boleta/factura).
 *
 * En Perú el precio al público incluye IGV; el sistema lo desglosa hacia atrás:
 *   base (op. gravada) = total / 1.18
 *   igv = total - base
 */
class ComprobanteIgvTest extends TestCase
{
    use RefreshDatabase;

    private VentaService $ventas;
    private User $vendedor;
    private int $cajaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->ventas = app(VentaService::class);

        $this->vendedor = User::factory()->create();
        $this->vendedor->assignRole(Rol::VENDEDOR->value);
        $this->actingAs($this->vendedor);

        $this->cajaId = app(CajaService::class)->abrir($this->vendedor->id, 0.0)->id;
    }

    private function productoConStock(float $precio, bool $afecto = true): Producto
    {
        $producto = Producto::factory()
            ->state(['precio_venta' => $precio, 'afecto_igv' => $afecto, 'activo' => true])
            ->create();
        Lote::factory()->vigente()->conStock(100)->create(['producto_id' => $producto->id]);

        return $producto;
    }

    // -------------------------------------------------------------------------
    // IGV
    // -------------------------------------------------------------------------

    public function test_venta_gravada_desglosa_igv_correctamente(): void
    {
        // Total 118 (precio incluye IGV) → base 100, IGV 18.
        $producto = $this->productoConStock(118.0);

        $venta = $this->ventas->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id,
            null,
            [],
            $this->cajaId,
        );

        $this->assertSame('118.00', (string) $venta->total);
        $this->assertSame('100.00', (string) $venta->subtotal); // op. gravada
        $this->assertSame('18.00',  (string) $venta->igv);
    }

    public function test_producto_exonerado_no_genera_igv(): void
    {
        $producto = $this->productoConStock(50.0, afecto: false);

        $venta = $this->ventas->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id,
            null,
            [],
            $this->cajaId,
        );

        $this->assertSame('50.00', (string) $venta->total);
        $this->assertSame('0.00', (string) $venta->subtotal); // sin base gravada
        $this->assertSame('0.00', (string) $venta->igv);
    }

    public function test_venta_mixta_gravado_y_exonerado_calcula_solo_el_igv_del_gravado(): void
    {
        $gravado   = $this->productoConStock(118.0, afecto: true);  // base 100 + igv 18
        $exonerado = $this->productoConStock(50.0, afecto: false);  // 50 exonerado

        $venta = $this->ventas->registrar(
            [
                ['producto_id' => $gravado->id, 'cantidad' => 1],
                ['producto_id' => $exonerado->id, 'cantidad' => 1],
            ],
            $this->vendedor->id,
            null,
            [],
            $this->cajaId,
        );

        $this->assertSame('168.00', (string) $venta->total);    // 118 + 50
        $this->assertSame('100.00', (string) $venta->subtotal); // solo base del gravado
        $this->assertSame('18.00',  (string) $venta->igv);      // solo IGV del gravado
        // Exonerada derivada = 168 - 100 - 18 = 50.
    }

    // -------------------------------------------------------------------------
    // Tipo de comprobante
    // -------------------------------------------------------------------------

    public function test_boleta_usa_serie_b001_por_defecto(): void
    {
        $producto = $this->productoConStock(20.0);

        $venta = $this->ventas->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id,
            null,
            [],
            $this->cajaId,
        );

        $this->assertSame('BOLETA', $venta->boleta->tipo_comprobante->value);
        $this->assertSame('B001', $venta->boleta->serie);
    }

    public function test_factura_requiere_cliente_con_ruc(): void
    {
        $producto = $this->productoConStock(20.0);

        // Cliente con DNI no puede recibir factura.
        $clienteDni = Cliente::factory()->create(['tipo_documento' => TipoDocumento::DNI]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('RUC');

        $this->ventas->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id,
            $clienteDni->id,
            [],
            $this->cajaId,
            null,
            'FACTURA',
        );
    }

    public function test_factura_con_cliente_ruc_usa_serie_f001(): void
    {
        $producto = $this->productoConStock(20.0);
        $clienteRuc = Cliente::factory()->ruc()->create();

        $venta = $this->ventas->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id,
            $clienteRuc->id,
            [],
            $this->cajaId,
            null,
            'FACTURA',
        );

        $this->assertSame('FACTURA', $venta->boleta->tipo_comprobante->value);
        $this->assertSame('F001', $venta->boleta->serie);
    }

    public function test_boleta_y_factura_llevan_correlativos_independientes(): void
    {
        $producto = $this->productoConStock(10.0);
        $clienteRuc = Cliente::factory()->ruc()->create();

        $b1 = $this->ventas->registrar([['producto_id' => $producto->id, 'cantidad' => 1]], $this->vendedor->id, null, [], $this->cajaId);
        $f1 = $this->ventas->registrar([['producto_id' => $producto->id, 'cantidad' => 1]], $this->vendedor->id, $clienteRuc->id, [], $this->cajaId, null, 'FACTURA');
        $b2 = $this->ventas->registrar([['producto_id' => $producto->id, 'cantidad' => 1]], $this->vendedor->id, null, [], $this->cajaId);

        // La factura no consume el correlativo de boletas.
        $this->assertSame($b1->boleta->numero + 1, $b2->boleta->numero);
        $this->assertSame(1, $f1->boleta->numero);
    }
}
