<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Rol;
use App\Enums\TipoMovimientoCaja;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Services\CajaService;
use App\Services\VentaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del Bloque 1: medios de pago, vínculo venta→caja, vuelto,
 * cuadre por efectivo y movimientos de caja.
 */
class PagoVentaCajaTest extends TestCase
{
    use RefreshDatabase;

    private VentaService $ventas;
    private CajaService $cajas;
    private User $vendedor;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->ventas = app(VentaService::class);
        $this->cajas  = app(CajaService::class);

        $this->vendedor = User::factory()->create();
        $this->vendedor->assignRole(Rol::VENDEDOR->value);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Rol::ADMINISTRADOR->value);

        $this->actingAs($this->vendedor);
    }

    private function productoConStock(float $precio = 10.0, int $stock = 50): Producto
    {
        $producto = Producto::factory()->create(['precio_venta' => $precio, 'activo' => true]);
        Lote::factory()->vigente()->conStock($stock)->create(['producto_id' => $producto->id]);

        return $producto;
    }

    // -------------------------------------------------------------------------
    // Pagos
    // -------------------------------------------------------------------------

    public function test_venta_persiste_pago_simple_efectivo(): void
    {
        $caja     = $this->cajas->abrir($this->vendedor->id, 0.0);
        $producto = $this->productoConStock(15.0);

        $venta = $this->ventas->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 2]],
            $this->vendedor->id,
            null,
            [['medio_pago' => 'EFECTIVO', 'monto' => 30.0]],
            $caja->id,
            50.0,
        );

        $this->assertDatabaseHas('pagos', [
            'venta_id'   => $venta->id,
            'medio_pago' => 'EFECTIVO',
            'monto'      => '30.00',
        ]);
        $this->assertSame('50.00', (string) $venta->monto_recibido);
        $this->assertSame('20.00', (string) $venta->vuelto); // 50 - 30
    }

    public function test_venta_persiste_pago_mixto_efectivo_mas_yape(): void
    {
        $caja     = $this->cajas->abrir($this->vendedor->id, 0.0);
        $producto = $this->productoConStock(50.0);

        $venta = $this->ventas->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 2]], // total 100
            $this->vendedor->id,
            null,
            [
                ['medio_pago' => 'EFECTIVO', 'monto' => 60.0],
                ['medio_pago' => 'YAPE', 'monto' => 40.0],
            ],
            $caja->id,
        );

        $this->assertDatabaseHas('pagos', ['venta_id' => $venta->id, 'medio_pago' => 'EFECTIVO', 'monto' => '60.00']);
        $this->assertDatabaseHas('pagos', ['venta_id' => $venta->id, 'medio_pago' => 'YAPE', 'monto' => '40.00']);
        $this->assertCount(2, $venta->pagos);
    }

    public function test_pagos_que_no_cubren_el_total_lanzan_excepcion(): void
    {
        $caja     = $this->cajas->abrir($this->vendedor->id, 0.0);
        $producto = $this->productoConStock(50.0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no coinciden con el total');

        $this->ventas->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 2]], // total 100
            $this->vendedor->id,
            null,
            [['medio_pago' => 'YAPE', 'monto' => 80.0]], // falta 20
            $caja->id,
        );
    }

    public function test_venta_sin_pagos_genera_efectivo_por_el_total(): void
    {
        // Comportamiento legacy: sin desglose, todo es efectivo.
        $caja     = $this->cajas->abrir($this->vendedor->id, 0.0);
        $producto = $this->productoConStock(25.0);

        $venta = $this->ventas->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id,
            null,
            [],
            $caja->id,
        );

        $this->assertDatabaseHas('pagos', [
            'venta_id'   => $venta->id,
            'medio_pago' => 'EFECTIVO',
            'monto'      => '25.00',
        ]);
    }

    // -------------------------------------------------------------------------
    // Cuadre por efectivo (lo central del bloque)
    // -------------------------------------------------------------------------

    public function test_cuadre_solo_cuenta_efectivo_no_los_pagos_digitales(): void
    {
        $caja     = $this->cajas->abrir($this->vendedor->id, 100.0);
        $producto = $this->productoConStock(50.0);

        // Venta 1: S/50 en EFECTIVO
        $this->ventas->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id,
            null,
            [['medio_pago' => 'EFECTIVO', 'monto' => 50.0]],
            $caja->id,
        );

        // Venta 2: S/50 en YAPE (NO entra a la gaveta)
        $this->ventas->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id,
            null,
            [['medio_pago' => 'YAPE', 'monto' => 50.0]],
            $caja->id,
        );

        // Efectivo esperado = apertura 100 + efectivo 50 = 150 (NO 200).
        // Si el cajero cuenta 150, la caja cuadra perfecto pese a la venta Yape.
        $cerrada = $this->cajas->cerrar($caja->fresh(), 150.0, $this->vendedor->id);

        $this->assertSame('100.00', (string) $cerrada->total_ventas);   // todos los medios
        $this->assertSame('150.00', (string) $cerrada->total_esperado); // solo efectivo
        $this->assertSame('0.00',   (string) $cerrada->diferencia);     // cuadra
    }

    public function test_egreso_de_caja_reduce_el_efectivo_esperado(): void
    {
        $caja     = $this->cajas->abrir($this->vendedor->id, 100.0);
        $producto = $this->productoConStock(40.0);

        // Venta de S/40 en efectivo.
        $this->ventas->registrar(
            [['producto_id' => $producto->id, 'cantidad' => 1]],
            $this->vendedor->id,
            null,
            [['medio_pago' => 'EFECTIVO', 'monto' => 40.0]],
            $caja->id,
        );

        // Retiro de S/30 de la gaveta para pagar al proveedor.
        $this->cajas->registrarMovimiento(
            $caja->fresh(),
            TipoMovimientoCaja::EGRESO,
            30.0,
            'Pago a droguería',
            $this->vendedor->id,
        );

        // Esperado = 100 + 40 - 30 = 110.
        $cerrada = $this->cajas->cerrar($caja->fresh(), 110.0, $this->vendedor->id);

        $this->assertSame('110.00', (string) $cerrada->total_esperado);
        $this->assertSame('0.00',   (string) $cerrada->diferencia);
    }

    public function test_ingreso_de_caja_aumenta_el_efectivo_esperado(): void
    {
        $caja = $this->cajas->abrir($this->vendedor->id, 50.0);

        $this->cajas->registrarMovimiento(
            $caja->fresh(),
            TipoMovimientoCaja::INGRESO,
            25.0,
            'Fondo adicional',
            $this->vendedor->id,
        );

        // Esperado = 50 + 25 = 75 (sin ventas).
        $cerrada = $this->cajas->cerrar($caja->fresh(), 75.0, $this->vendedor->id);

        $this->assertSame('75.00', (string) $cerrada->total_esperado);
        $this->assertSame('0.00',  (string) $cerrada->diferencia);
    }

    public function test_no_se_registra_movimiento_en_caja_cerrada(): void
    {
        $caja = $this->cajas->abrir($this->vendedor->id, 50.0);
        $this->cajas->cerrar($caja->fresh(), 50.0, $this->vendedor->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('caja cerrada');

        $this->cajas->registrarMovimiento(
            $caja->fresh(),
            TipoMovimientoCaja::EGRESO,
            10.0,
            'Intento sobre caja cerrada',
            $this->vendedor->id,
        );
    }

    public function test_desglose_por_medio_agrupa_correctamente(): void
    {
        $caja     = $this->cajas->abrir($this->vendedor->id, 0.0);
        $producto = $this->productoConStock(20.0);

        // 2 efectivo (40) + 1 yape (20)
        $this->ventas->registrar([['producto_id' => $producto->id, 'cantidad' => 1]], $this->vendedor->id, null, [['medio_pago' => 'EFECTIVO', 'monto' => 20.0]], $caja->id);
        $this->ventas->registrar([['producto_id' => $producto->id, 'cantidad' => 1]], $this->vendedor->id, null, [['medio_pago' => 'EFECTIVO', 'monto' => 20.0]], $caja->id);
        $this->ventas->registrar([['producto_id' => $producto->id, 'cantidad' => 1]], $this->vendedor->id, null, [['medio_pago' => 'YAPE', 'monto' => 20.0]], $caja->id);

        $desglose = $this->cajas->desglosePorMedio($caja->fresh());

        $this->assertSame(40.0, $desglose['EFECTIVO']);
        $this->assertSame(20.0, $desglose['YAPE']);
        $this->assertSame(0.0,  $desglose['TARJETA']);
    }

    // -------------------------------------------------------------------------
    // HTTP movimiento
    // -------------------------------------------------------------------------

    public function test_vendedor_registra_movimiento_via_endpoint(): void
    {
        $caja = $this->cajas->abrir($this->vendedor->id, 100.0);

        $response = $this->post(route('cajas.movimiento', $caja), [
            'tipo'     => 'EGRESO',
            'monto'    => 25.0,
            'concepto' => 'Compra de bolsas',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('movimientos_caja', [
            'caja_id'  => $caja->id,
            'tipo'     => 'EGRESO',
            'monto'    => '25.00',
            'concepto' => 'Compra de bolsas',
        ]);
    }

    public function test_vendedor_no_registra_movimiento_en_caja_ajena(): void
    {
        $otro = User::factory()->create();
        $otro->assignRole(Rol::VENDEDOR->value);
        $cajaAjena = $this->cajas->abrir($otro->id, 100.0);

        $response = $this->post(route('cajas.movimiento', $cajaAjena), [
            'tipo'     => 'EGRESO',
            'monto'    => 25.0,
            'concepto' => 'Intento sobre caja ajena',
        ]);

        $response->assertStatus(403);
    }
}
