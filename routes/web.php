<?php

declare(strict_types=1);

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'permission:dashboard.read'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Perfil del usuario.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | Categorías
    |--------------------------------------------------------------------------
    */
    Route::get('categorias', [CategoriaController::class, 'index'])
        ->name('categorias.index')->middleware('permission:categorias.read');
    Route::post('categorias', [CategoriaController::class, 'store'])
        ->name('categorias.store')->middleware('permission:categorias.create');
    Route::put('categorias/{categoria}', [CategoriaController::class, 'update'])
        ->name('categorias.update')->middleware('permission:categorias.update');
    Route::delete('categorias/{categoria}', [CategoriaController::class, 'destroy'])
        ->name('categorias.destroy')->middleware('permission:categorias.delete');

    /*
    |--------------------------------------------------------------------------
    | Proveedores
    |--------------------------------------------------------------------------
    */
    Route::get('proveedores', [ProveedorController::class, 'index'])
        ->name('proveedores.index')->middleware('permission:proveedores.read');
    Route::post('proveedores', [ProveedorController::class, 'store'])
        ->name('proveedores.store')->middleware('permission:proveedores.create');
    Route::put('proveedores/{proveedor}', [ProveedorController::class, 'update'])
        ->name('proveedores.update')->middleware('permission:proveedores.update');
    Route::delete('proveedores/{proveedor}', [ProveedorController::class, 'destroy'])
        ->name('proveedores.destroy')->middleware('permission:proveedores.delete');

    /*
    |--------------------------------------------------------------------------
    | Clientes
    |--------------------------------------------------------------------------
    */
    Route::get('clientes', [ClienteController::class, 'index'])
        ->name('clientes.index')->middleware('permission:clientes.read');
    Route::post('clientes', [ClienteController::class, 'store'])
        ->name('clientes.store')->middleware('permission:clientes.create');
    Route::put('clientes/{cliente}', [ClienteController::class, 'update'])
        ->name('clientes.update')->middleware('permission:clientes.update');
    Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy'])
        ->name('clientes.destroy')->middleware('permission:clientes.delete');

    /*
    |--------------------------------------------------------------------------
    | Productos
    |--------------------------------------------------------------------------
    */
    Route::get('productos', [ProductoController::class, 'index'])
        ->name('productos.index')->middleware('permission:productos.read');
    Route::post('productos', [ProductoController::class, 'store'])
        ->name('productos.store')->middleware('permission:productos.create');
    Route::put('productos/{producto}', [ProductoController::class, 'update'])
        ->name('productos.update')->middleware('permission:productos.update');
    Route::delete('productos/{producto}', [ProductoController::class, 'destroy'])
        ->name('productos.destroy')->middleware('permission:productos.delete');

    /*
    |--------------------------------------------------------------------------
    | Lotes / Inventario
    |--------------------------------------------------------------------------
    */
    Route::get('lotes', [LoteController::class, 'index'])
        ->name('lotes.index')->middleware('permission:lotes.read');
    Route::post('lotes', [LoteController::class, 'store'])
        ->name('lotes.store')->middleware('permission:lotes.create');
    Route::put('lotes/{lote}', [LoteController::class, 'update'])
        ->name('lotes.update')->middleware('permission:lotes.update');
    Route::delete('lotes/{lote}', [LoteController::class, 'destroy'])
        ->name('lotes.destroy')->middleware('permission:lotes.delete');

    /*
    |--------------------------------------------------------------------------
    | Movimientos de inventario (kardex)
    |--------------------------------------------------------------------------
    */
    Route::get('inventario/movimientos', [MovimientoInventarioController::class, 'index'])
        ->name('inventario.movimientos.index')->middleware('permission:inventario.read');
    Route::post('inventario/movimientos', [MovimientoInventarioController::class, 'store'])
        ->name('inventario.movimientos.store')->middleware('permission:inventario.create');

    /*
    |--------------------------------------------------------------------------
    | Compras (órdenes a proveedor + recepción)
    |--------------------------------------------------------------------------
    */
    Route::get('compras', [CompraController::class, 'index'])
        ->name('compras.index')->middleware('permission:compras.read');
    Route::get('compras/nueva', [CompraController::class, 'create'])
        ->name('compras.create')->middleware('permission:compras.create');
    Route::post('compras', [CompraController::class, 'store'])
        ->name('compras.store')->middleware('permission:compras.create');
    Route::get('compras/{compra}', [CompraController::class, 'show'])
        ->name('compras.show')->middleware('permission:compras.read');
    Route::get('compras/{compra}/edit', [CompraController::class, 'edit'])
        ->name('compras.edit')->middleware('permission:compras.update');
    Route::put('compras/{compra}', [CompraController::class, 'update'])
        ->name('compras.update')->middleware('permission:compras.update');
    Route::put('compras/{compra}/recibir', [CompraController::class, 'recibir'])
        ->name('compras.recibir')->middleware('permission:compras.recibir');
    Route::delete('compras/{compra}', [CompraController::class, 'destroy'])
        ->name('compras.destroy')->middleware('permission:compras.delete');

    /*
    |--------------------------------------------------------------------------
    | Usuarios
    |--------------------------------------------------------------------------
    */
    Route::get('usuarios', [UsuarioController::class, 'index'])
        ->name('usuarios.index')->middleware('permission:usuarios.read');
    Route::post('usuarios', [UsuarioController::class, 'store'])
        ->name('usuarios.store')->middleware('permission:usuarios.create');
    Route::put('usuarios/{user}', [UsuarioController::class, 'update'])
        ->name('usuarios.update')->middleware('permission:usuarios.update');
    Route::delete('usuarios/{user}', [UsuarioController::class, 'destroy'])
        ->name('usuarios.destroy')->middleware('permission:usuarios.delete');

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */
    Route::get('roles', [RolController::class, 'index'])
        ->name('roles.index')->middleware('permission:roles.read');
    Route::post('roles', [RolController::class, 'store'])
        ->name('roles.store')->middleware('permission:roles.create');
    Route::put('roles/{role}', [RolController::class, 'update'])
        ->name('roles.update')->middleware('permission:roles.update');
    Route::delete('roles/{role}', [RolController::class, 'destroy'])
        ->name('roles.destroy')->middleware('permission:roles.delete');

    /*
    |--------------------------------------------------------------------------
    | Auditoría (solo lectura)
    |--------------------------------------------------------------------------
    */
    Route::get('auditoria', [AuditoriaController::class, 'index'])
        ->name('auditoria.index')->middleware('permission:auditoria.read');

    /*
    |--------------------------------------------------------------------------
    | Configuración de la empresa
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:empresa.update')->group(function () {
        Route::get('configuracion', [EmpresaController::class, 'edit'])->name('configuracion.edit');
        Route::put('configuracion', [EmpresaController::class, 'update'])->name('configuracion.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Ventas
    |--------------------------------------------------------------------------
    */
    Route::get('ventas/nueva', [VentaController::class, 'create'])
        ->name('ventas.create')->middleware('permission:ventas.create');
    Route::post('ventas', [VentaController::class, 'store'])
        ->name('ventas.store')->middleware('permission:ventas.create');
    Route::get('ventas', [VentaController::class, 'index'])
        ->name('ventas.index')->middleware('permission:ventas.read');
    Route::get('ventas/{venta}/boleta', [VentaController::class, 'boleta'])
        ->name('ventas.boleta')->middleware('permission:ventas.read');
    Route::get('ventas/{venta}/boleta.pdf', [VentaController::class, 'boletaPdf'])
        ->name('ventas.boleta.pdf')->middleware('permission:ventas.read');
    Route::put('ventas/{venta}/anular', [VentaController::class, 'anular'])
        ->name('ventas.anular')->middleware('permission:ventas.cancel');

    /*
    |--------------------------------------------------------------------------
    | Reportes (exportación a Excel)
    |--------------------------------------------------------------------------
    */
    // Rate limit dedicado: los exports son caros (memoria/CPU); 10 descargas
    // por minuto y por usuario alcanza para uso operativo y previene abuso.
    Route::middleware(['permission:reportes.read', 'throttle:10,1'])->prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/', [ReporteController::class, 'index'])->name('index');
        Route::get('/ventas-por-periodo', [ReporteController::class, 'ventasPorPeriodo'])->name('ventasPorPeriodo');
        Route::get('/mas-vendidos', [ReporteController::class, 'productosMasVendidos'])->name('productosMasVendidos');
        Route::get('/por-vencer', [ReporteController::class, 'productosPorVencer'])->name('productosPorVencer');
        Route::get('/stock-bajo', [ReporteController::class, 'lotesStockBajo'])->name('lotesStockBajo');
        Route::get('/kardex', [ReporteController::class, 'kardex'])->name('kardex');
        Route::get('/auditoria', [ReporteController::class, 'auditoria'])->name('auditoria');
    });
});

require __DIR__.'/auth.php';
