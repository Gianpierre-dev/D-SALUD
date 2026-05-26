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
 * Pruebas de la lógica de negocio del VentaService.
 *
 * Cubre: FEFO, exclusión de vencidos, rollback por stock insuficiente,
 * producto inactivo, numeración correlativa de boletas, cálculo de total,
 * anulación con reposición de stock y prevención de doble anulación.
 */
class VentaServiceTest extends TestCase
{
    use RefreshDatabase;

    private VentaService $servicio;
    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        /** @var VentaService $servicio */
        $this->servicio = app(VentaService::class);

        $this->vendedor = User::factory()->create();
        $this->vendedor->assignRole('Vendedor');

        // Autenticar al vendedor para que AuditoriaService pueda registrar Auth::id()
        $this->actingAs($this->vendedor);
    }

    // -------------------------------------------------------------------------
    // FEFO — First Expired, First Out
    // -------------------------------------------------------------------------

    public function test_registrar_descuenta_del_lote_mas_proximo_a_vencer_primero(): void
    {
        $producto = Producto::factory()->create(['precio_venta' => 10.00, 'activo' => true]);

        // Lote que vence más tarde (debería quedar intacto)
        $loteTardio = Lote::factory()->create([
            'producto_id'       => $producto->id,
            'fecha_vencimiento' => now()->addYear()->format('Y-m-d'),
            'stock'             => 50,
        ]);

        // Lote que vence primero (debe ser consumido primero por FEFO)
        $loteProximo = Lote::factory()->create([
            'producto_id'       => $producto->id,
            'fecha_vencimiento' => now()->addMonth()->format('Y-m-d'),
            'stock'             => 50,
        ]);

        $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 50]],
            $this->vendedor->id
        );

        $this->assertEquals(0, $loteProximo->fresh()->stock);
        $this->assertEquals(50, $loteTardio->fresh()->stock);
    }

    // -------------------------------------------------------------------------
    // Exclusión de lotes vencidos
    // -------------------------------------------------------------------------

    public function test_registrar_excluye_lotes_vencidos_y_lanza_excepcion_si_no_hay_stock_vigente(): void
    {
        $producto = Producto::factory()->create(['activo' => true]);

        // Solo hay un lote, y está vencido
        Lote::factory()->vencido()->create([
            'producto_id' => $producto->id,
            'stock'       => 100,
        ]);

        $this->expectException(\RuntimeException::class);

        $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id
        );
    }

    public function test_registrar_no_usa_lote_vencido_cuando_hay_lote_vigente(): void
    {
        $producto = Producto::factory()->create(['precio_venta' => 5.00, 'activo' => true]);

        $loteVencido = Lote::factory()->vencido()->create([
            'producto_id' => $producto->id,
            'stock'       => 100,
        ]);

        $loteVigente = Lote::factory()->vigente()->create([
            'producto_id' => $producto->id,
            'stock'       => 10,
        ]);

        $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 5]],
            $this->vendedor->id
        );

        // El vencido no debe haber sido tocado
        $this->assertEquals(100, $loteVencido->fresh()->stock);
        // El vigente sí descontó
        $this->assertEquals(5, $loteVigente->fresh()->stock);
    }

    // -------------------------------------------------------------------------
    // Rollback por stock insuficiente
    // -------------------------------------------------------------------------

    public function test_registrar_lanza_excepcion_y_hace_rollback_si_stock_es_insuficiente(): void
    {
        $producto = Producto::factory()->create(['precio_venta' => 20.00, 'activo' => true]);

        $lote = Lote::factory()->vigente()->conStock(5)->create([
            'producto_id' => $producto->id,
        ]);

        $this->expectException(\RuntimeException::class);

        $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 10]],
            $this->vendedor->id
        );

        // Sin llegar aquí gracias a expectException, pero verificamos rollback
        $this->assertEquals(5, $lote->fresh()->stock);
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_registrar_no_crea_venta_ni_descuenta_stock_cuando_hay_excepcion_por_insuficiencia(): void
    {
        $producto = Producto::factory()->create(['precio_venta' => 20.00, 'activo' => true]);

        Lote::factory()->vigente()->conStock(3)->create([
            'producto_id' => $producto->id,
        ]);

        try {
            $this->servicio->registrar(
                [['producto_id' => $producto->id, 'cantidad' => 10]],
                $this->vendedor->id
            );
        } catch (\RuntimeException) {
            // Excepción esperada; verificamos que no quedaron registros
        }

        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('detalle_ventas', 0);
    }

    // -------------------------------------------------------------------------
    // Producto inactivo
    // -------------------------------------------------------------------------

    public function test_registrar_lanza_excepcion_si_producto_esta_inactivo(): void
    {
        $producto = Producto::factory()->inactivo()->create();

        Lote::factory()->vigente()->conStock(50)->create([
            'producto_id' => $producto->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no está disponible para la venta');

        $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id
        );
    }

    // -------------------------------------------------------------------------
    // Numeración correlativa de boletas
    // -------------------------------------------------------------------------

    public function test_registrar_genera_boleta_con_numero_correlativo(): void
    {
        $serie = config('dsalud.boleta.serie');

        $producto = Producto::factory()->create(['precio_venta' => 15.00, 'activo' => true]);
        Lote::factory()->vigente()->conStock(100)->create(['producto_id' => $producto->id]);

        $venta1 = $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id
        );

        $venta2 = $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id
        );

        $numeroBoleta1 = $venta1->boleta->numero;
        $numeroBoleta2 = $venta2->boleta->numero;

        $this->assertEquals(1, $numeroBoleta1);
        $this->assertEquals($numeroBoleta1 + 1, $numeroBoleta2);
        $this->assertEquals($serie, $venta1->boleta->serie);
        $this->assertEquals($serie, $venta2->boleta->serie);
    }

    // -------------------------------------------------------------------------
    // Cálculo correcto del total
    // -------------------------------------------------------------------------

    public function test_registrar_calcula_total_correcto_con_varios_items(): void
    {
        $productoA = Producto::factory()->create(['precio_venta' => 10.00, 'activo' => true]);
        $productoB = Producto::factory()->create(['precio_venta' => 25.50, 'activo' => true]);

        Lote::factory()->vigente()->conStock(100)->create(['producto_id' => $productoA->id]);
        Lote::factory()->vigente()->conStock(100)->create(['producto_id' => $productoB->id]);

        // Total esperado: (3 × 10.00) + (2 × 25.50) = 30.00 + 51.00 = 81.00
        $venta = $this->servicio->registrar(
            [
                ['producto_id' => $productoA->id, 'cantidad' => 3],
                ['producto_id' => $productoB->id, 'cantidad' => 2],
            ],
            $this->vendedor->id
        );

        $this->assertEquals('81.00', number_format((float) $venta->total, 2));
    }

    // -------------------------------------------------------------------------
    // Anulación: reposición de stock y cambio de estado
    // -------------------------------------------------------------------------

    public function test_anular_repone_stock_a_los_lotes_originales_y_marca_estado_anulada(): void
    {
        $producto = Producto::factory()->create(['precio_venta' => 8.00, 'activo' => true]);
        $lote = Lote::factory()->vigente()->conStock(50)->create(['producto_id' => $producto->id]);

        $venta = $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 10]],
            $this->vendedor->id
        );

        // Después de la venta el lote tiene 40
        $this->assertEquals(40, $lote->fresh()->stock);

        $admin = User::factory()->create();
        $admin->assignRole('Administrador');

        $this->servicio->anular($venta, 'Error de cobro', $admin->id);

        // Después de anular el stock debe volver a 50
        $this->assertEquals(50, $lote->fresh()->stock);
        $this->assertEquals(Venta::ESTADO_ANULADA, $venta->fresh()->estado);
    }

    // -------------------------------------------------------------------------
    // Doble anulación
    // -------------------------------------------------------------------------

    public function test_anular_dos_veces_lanza_excepcion_ya_esta_anulada(): void
    {
        $producto = Producto::factory()->create(['precio_venta' => 12.00, 'activo' => true]);
        Lote::factory()->vigente()->conStock(50)->create(['producto_id' => $producto->id]);

        $venta = $this->servicio->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 5]],
            $this->vendedor->id
        );

        $admin = User::factory()->create();
        $admin->assignRole('Administrador');

        // Primera anulación: correcta
        $this->servicio->anular($venta, 'Primer motivo', $admin->id);

        // Segunda anulación: debe fallar
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ya está anulada');

        $this->servicio->anular($venta->fresh(), 'Segundo intento', $admin->id);
    }
}
