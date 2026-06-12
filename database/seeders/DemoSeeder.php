<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MotivoMovimiento;
use App\Enums\Rol;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use App\Services\CajaService;
use App\Services\CompraService;
use App\Services\MovimientoInventarioService;
use App\Services\VentaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de demostración para presentaciones (jurado, demos comerciales).
 *
 * NO forma parte de DatabaseSeeder: se ejecuta UNA sola vez, a demanda:
 *   php artisan db:seed --class=DemoSeeder --force
 *
 * Todo el flujo usa los services reales (VentaService, CompraService,
 * CajaService, MovimientoInventarioService) para que FEFO, kardex, IGV,
 * correlativos y pagos queden 100% consistentes. Las ventas históricas se
 * retrodatan para que el dashboard muestre tendencia de 14 días.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Producto::count() > 0) {
            $this->command?->warn('Ya existen productos; DemoSeeder no se ejecuta (evita duplicados).');

            return;
        }

        $admin = User::where('email', 'admin@dsalud.com')->first() ?? User::firstOrFail();
        $vendedor = $this->crearVendedor();

        $categorias  = $this->crearCategorias();
        $proveedores = $this->crearProveedores();
        $productos   = $this->crearProductosConStock($categorias, $proveedores, $admin->id);
        $clientes    = $this->crearClientes();

        $this->crearCompras($productos, $proveedores, $admin->id);
        $this->crearVentasHistoricas($productos, $clientes, $admin, $vendedor);
        $this->crearCajaDeHoy($productos, $vendedor);

        $this->command?->info('Datos de demostración cargados correctamente.');
    }

    private function crearVendedor(): User
    {
        $vendedor = User::firstOrCreate(
            ['email' => 'vendedor@dsalud.com'],
            ['name' => 'María Torres', 'password' => Hash::make('123456')],
        );
        $vendedor->syncRoles(Rol::VENDEDOR->value);

        return $vendedor;
    }

    /** @return array<string, Categoria> */
    private function crearCategorias(): array
    {
        $nombres = [
            'Analgésicos'             => 'Alivio del dolor y fiebre',
            'Antibióticos'            => 'Venta con receta médica',
            'Antiinflamatorios'       => 'Tratamiento de inflamación',
            'Vitaminas y suplementos' => 'Suplementos nutricionales',
            'Cuidado personal'        => 'Higiene y cuidado diario',
            'Primeros auxilios'       => 'Material de curación',
        ];

        $categorias = [];
        foreach ($nombres as $nombre => $descripcion) {
            $categorias[$nombre] = Categoria::create([
                'nombre' => $nombre, 'descripcion' => $descripcion, 'activo' => true,
            ]);
        }

        return $categorias;
    }

    /** @return list<Proveedor> */
    private function crearProveedores(): array
    {
        $datos = [
            ['ruc' => '20603040504', 'razon_social' => 'Droguería Alfaro S.A.C.', 'contacto' => 'Carlos Alfaro', 'telefono' => '014567890'],
            ['ruc' => '20601589428', 'razon_social' => 'Distribuidora Quimfar S.A.C.', 'contacto' => 'Rosa Mendoza', 'telefono' => '013456789'],
            ['ruc' => '20100018625', 'razon_social' => 'Laboratorios Induquímica S.A.', 'contacto' => 'Jorge Salas', 'telefono' => '012345678'],
        ];

        return array_map(
            static fn (array $p): Proveedor => Proveedor::create($p + ['activo' => true]),
            $datos,
        );
    }

    /**
     * Crea productos con su lote inicial. El stock entra por el kardex
     * (motivo INVENTARIO_INICIAL) para que la trazabilidad sea completa.
     *
     * @param  array<string, Categoria>  $categorias
     * @param  list<Proveedor>  $proveedores
     * @return list<Producto>
     */
    private function crearProductosConStock(array $categorias, array $proveedores, int $userId): array
    {
        $kardex = app(MovimientoInventarioService::class);

        // [codigo, nombre, categoria, laboratorio, unidad, precio, afecto_igv, stock_min, stock, vence_en_dias]
        $filas = [
            ['MED-001', 'Paracetamol 500 mg x 10 tab', 'Analgésicos', 'Genfar', 'blíster', 3.50, true, 20, 180, 540],
            ['MED-002', 'Ibuprofeno 400 mg x 10 tab', 'Antiinflamatorios', 'Portugal', 'blíster', 4.00, true, 20, 150, 480],
            ['MED-003', 'Amoxicilina 500 mg x 10 cap', 'Antibióticos', 'Medifarma', 'blíster', 8.50, true, 15, 90, 420],
            ['MED-004', 'Azitromicina 500 mg x 3 tab', 'Antibióticos', 'IQFarma', 'blíster', 12.00, true, 10, 60, 390],
            ['MED-005', 'Omeprazol 20 mg x 14 cap', 'Analgésicos', 'Genfar', 'blíster', 6.50, true, 15, 110, 600],
            ['MED-006', 'Loratadina 10 mg x 10 tab', 'Analgésicos', 'Portugal', 'blíster', 5.00, true, 15, 95, 510],
            ['MED-007', 'Metformina 850 mg x 30 tab', 'Analgésicos', 'Medifarma', 'caja', 9.80, false, 12, 70, 450],
            ['MED-008', 'Losartán 50 mg x 30 tab', 'Analgésicos', 'IQFarma', 'caja', 11.50, false, 12, 65, 470],
            ['VIT-001', 'Complejo B x 30 tab', 'Vitaminas y suplementos', 'Bayer', 'frasco', 15.00, true, 10, 55, 700],
            ['VIT-002', 'Vitamina C 1 g x 10 tab efervescente', 'Vitaminas y suplementos', 'Bayer', 'tubo', 9.00, true, 10, 80, 650],
            ['VIT-003', 'Ensure Advance Vainilla 850 g', 'Vitaminas y suplementos', 'Abbott', 'lata', 98.00, true, 5, 25, 540],
            ['CUI-001', 'Alcohol etílico 70° x 1 L', 'Cuidado personal', 'Portugal', 'frasco', 8.00, true, 10, 60, 720],
            ['CUI-002', 'Jabón antibacterial x 90 g', 'Cuidado personal', 'Medifarma', 'unidad', 4.50, true, 12, 75, 720],
            ['CUI-003', 'Bloqueador solar FPS 50+ x 120 ml', 'Cuidado personal', 'Bayer', 'frasco', 42.00, true, 6, 30, 600],
            ['AUX-001', 'Algodón hidrófilo x 50 g', 'Primeros auxilios', 'Genfar', 'paquete', 3.00, true, 15, 100, 900],
            ['AUX-002', 'Gasa estéril 10x10 cm x 10 und', 'Primeros auxilios', 'Medifarma', 'paquete', 6.00, true, 10, 3, 800],
            ['AUX-003', 'Termómetro digital', 'Primeros auxilios', 'Omron', 'unidad', 25.00, true, 5, 2, 1800],
            ['AUX-004', 'Suero fisiológico 9‰ x 1 L', 'Primeros auxilios', 'Medifarma', 'frasco', 7.50, true, 10, 40, 25],
        ];

        $productos = [];
        foreach ($filas as $i => [$codigo, $nombre, $cat, $lab, $unidad, $precio, $afecto, $minimo, $stock, $venceDias]) {
            $producto = Producto::create([
                'codigo'        => $codigo,
                'nombre'        => $nombre,
                'categoria_id'  => $categorias[$cat]->id,
                'laboratorio'   => $lab,
                'unidad_medida' => $unidad,
                'precio_venta'  => $precio,
                'afecto_igv'    => $afecto,
                'stock_minimo'  => $minimo,
                'activo'        => true,
            ]);

            $lote = Lote::create([
                'producto_id'       => $producto->id,
                'proveedor_id'      => $proveedores[$i % count($proveedores)]->id,
                'codigo_lote'       => sprintf('L%s-2026A', substr($codigo, 4)),
                'fecha_vencimiento' => Carbon::today()->addDays($venceDias),
                'stock'             => 0,
                'precio_compra'     => round($precio * 0.6, 2),
            ]);

            // El stock entra por el kardex: trazabilidad completa desde el día 1.
            $kardex->registrarEntrada(
                $lote,
                MotivoMovimiento::INVENTARIO_INICIAL,
                $stock,
                'Carga inicial de inventario',
                null,
                $userId,
            );

            $productos[] = $producto;
        }

        return $productos;
    }

    /** @return list<Cliente> */
    private function crearClientes(): array
    {
        $datos = [
            ['tipo_documento' => 'DNI', 'numero_documento' => '45871236', 'nombre' => 'Lucía Fernández Ramos', 'telefono' => '987654321'],
            ['tipo_documento' => 'DNI', 'numero_documento' => '72654980', 'nombre' => 'Pedro Quispe Huamán', 'telefono' => '912345678'],
            ['tipo_documento' => 'DNI', 'numero_documento' => '40328765', 'nombre' => 'Carmen Soto Vega', 'telefono' => '956781234'],
            ['tipo_documento' => 'DNI', 'numero_documento' => '08761234', 'nombre' => 'José Cárdenas Ríos', 'telefono' => '934567812'],
            ['tipo_documento' => 'RUC', 'numero_documento' => '10456789019', 'nombre' => 'Consultorio Médico San Martín E.I.R.L.', 'telefono' => '014789632'],
            ['tipo_documento' => 'RUC', 'numero_documento' => '20603040504', 'nombre' => 'Clínica Dental Sonrisa S.A.C.', 'telefono' => '015632147'],
        ];

        return array_map(
            static fn (array $c): Cliente => Cliente::create($c + ['activo' => true]),
            $datos,
        );
    }

    /**
     * Una compra RECIBIDA (genera lotes y kardex de ENTRADA) y una PENDIENTE
     * para que el módulo muestre ambos estados del flujo.
     *
     * @param  list<Producto>  $productos
     * @param  list<Proveedor>  $proveedores
     */
    private function crearCompras(array $productos, array $proveedores, int $userId): void
    {
        $compras = app(CompraService::class);

        $recibida = $compras->crear([
            'proveedor_id'  => $proveedores[0]->id,
            'fecha_compra'  => Carbon::today()->subDays(9)->format('Y-m-d'),
            'observaciones' => 'Reposición quincenal de analgésicos',
            'items'         => [
                ['producto_id' => $productos[0]->id, 'cantidad' => 100, 'precio_unitario' => 2.10, 'codigo_lote' => 'L001-2026B', 'fecha_vencimiento' => Carbon::today()->addDays(560)->format('Y-m-d')],
                ['producto_id' => $productos[1]->id, 'cantidad' => 80, 'precio_unitario' => 2.40, 'codigo_lote' => 'L002-2026B', 'fecha_vencimiento' => Carbon::today()->addDays(500)->format('Y-m-d')],
            ],
        ], $userId);
        $compras->recibir($recibida->fresh(), $userId);

        $fechaRecibida = Carbon::today()->subDays(9)->setTime(11, 30);
        DB::table('compras')->where('id', $recibida->id)
            ->update(['created_at' => $fechaRecibida, 'updated_at' => $fechaRecibida]);
        DB::table('movimientos_inventario')
            ->where('referencia_tipo', 'compra')->where('referencia_id', $recibida->id)
            ->update(['created_at' => $fechaRecibida, 'updated_at' => $fechaRecibida]);

        $compras->crear([
            'proveedor_id'  => $proveedores[1]->id,
            'fecha_compra'  => Carbon::today()->format('Y-m-d'),
            'observaciones' => 'Pedido de vitaminas en tránsito',
            'items'         => [
                ['producto_id' => $productos[8]->id, 'cantidad' => 40, 'precio_unitario' => 9.00, 'codigo_lote' => 'LV01-2026B', 'fecha_vencimiento' => Carbon::today()->addDays(700)->format('Y-m-d')],
                ['producto_id' => $productos[10]->id, 'cantidad' => 12, 'precio_unitario' => 62.00, 'codigo_lote' => 'LV03-2026B', 'fecha_vencimiento' => Carbon::today()->addDays(540)->format('Y-m-d')],
            ],
        ], $userId);
    }

    /**
     * Ventas de los últimos 13 días (sin caja, retrodatadas) con medios de
     * pago variados, clientes ocasionales y un par de facturas.
     *
     * @param  list<Producto>  $productos
     * @param  list<Cliente>  $clientes
     */
    private function crearVentasHistoricas(array $productos, array $clientes, User $admin, User $vendedor): void
    {
        $ventas = app(VentaService::class);
        // Productos "vendibles" (excluye los de stock bajo: gasa y termómetro).
        $vendibles = array_values(array_filter(
            $productos,
            static fn (Producto $p): bool => ! in_array($p->codigo, ['AUX-002', 'AUX-003'], true),
        ));
        $medios = ['EFECTIVO', 'EFECTIVO', 'EFECTIVO', 'YAPE', 'YAPE', 'TARJETA', 'PLIN'];

        mt_srand(20260611); // datos reproducibles

        for ($dia = 13; $dia >= 1; $dia--) {
            $ventasDelDia = mt_rand(1, 4);

            for ($v = 0; $v < $ventasDelDia; $v++) {
                $items = [];
                $total = 0.0;
                $usados = (array) array_rand($vendibles, mt_rand(1, 3));
                foreach ($usados as $idx) {
                    $cantidad = mt_rand(1, 3);
                    $items[] = ['producto_id' => $vendibles[$idx]->id, 'cantidad' => $cantidad];
                    $total += round($cantidad * (float) $vendibles[$idx]->precio_venta, 2);
                }
                $total = round($total, 2);

                // 1 de cada 3 ventas con cliente; factura solo para clientes RUC.
                $cliente = mt_rand(1, 3) === 1 ? $clientes[array_rand($clientes)] : null;
                $tipo = ($cliente && $cliente->tipo_documento->value === 'RUC') ? 'FACTURA' : 'BOLETA';

                $medio = $medios[array_rand($medios)];
                $pagos = [['medio_pago' => $medio, 'monto' => $total]];
                $recibido = $medio === 'EFECTIVO' ? (float) (ceil($total / 10) * 10) : null;

                $usuario = mt_rand(0, 1) === 0 ? $admin : $vendedor;

                $venta = $ventas->registrar($items, $usuario->id, $cliente?->id, $pagos, null, $recibido, $tipo);

                $fecha = Carbon::today()->subDays($dia)->setTime(mt_rand(9, 19), mt_rand(0, 59));
                $this->retrodatarVenta($venta, $fecha);
            }
        }
    }

    /**
     * Caja del día: el vendedor abre turno y registra ventas vinculadas,
     * queda ABIERTA para mostrar el flujo de cierre en vivo.
     *
     * @param  list<Producto>  $productos
     */
    private function crearCajaDeHoy(array $productos, User $vendedor): void
    {
        $cajas  = app(CajaService::class);
        $ventas = app(VentaService::class);

        $caja = $cajas->abrir($vendedor->id, 150.00);

        $operaciones = [
            [['producto_id' => $productos[0]->id, 'cantidad' => 2], 'EFECTIVO'],
            [['producto_id' => $productos[9]->id, 'cantidad' => 1], 'YAPE'],
            [['producto_id' => $productos[4]->id, 'cantidad' => 1], 'EFECTIVO'],
        ];

        foreach ($operaciones as [$item, $medio]) {
            $producto = Producto::findOrFail($item['producto_id']);
            $total = round($item['cantidad'] * (float) $producto->precio_venta, 2);
            $recibido = $medio === 'EFECTIVO' ? (float) (ceil($total / 10) * 10) : null;

            $ventas->registrar(
                [$item],
                $vendedor->id,
                null,
                [['medio_pago' => $medio, 'monto' => $total]],
                $caja->id,
                $recibido,
            );
        }
    }

    /**
     * Retrodata una venta y todos sus registros asociados (boleta, pagos y
     * movimientos de kardex) para construir el histórico del dashboard.
     */
    private function retrodatarVenta(Venta $venta, Carbon $fecha): void
    {
        DB::table('ventas')->where('id', $venta->id)
            ->update(['created_at' => $fecha, 'updated_at' => $fecha]);

        DB::table('boletas')->where('venta_id', $venta->id)
            ->update(['created_at' => $fecha, 'updated_at' => $fecha]);

        DB::table('pagos')->where('venta_id', $venta->id)
            ->update(['created_at' => $fecha, 'updated_at' => $fecha]);

        DB::table('movimientos_inventario')
            ->where('referencia_tipo', 'venta')->where('referencia_id', $venta->id)
            ->update(['created_at' => $fecha, 'updated_at' => $fecha]);
    }
}
