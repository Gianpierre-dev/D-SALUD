<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kardex: registra TODO cambio de stock como un evento inmutable.
     *
     * - `tipo` indica si el movimiento suma (ENTRADA) o resta (SALIDA).
     * - `motivo` clasifica el origen (VENTA, COMPRA, MERMA, etc.).
     * - `cantidad` SIEMPRE es positiva; el signo lo da `tipo`.
     * - `stock_anterior`/`stock_posterior` son snapshots para auditar
     *   inconsistencias contra la columna `lotes.stock` (caché).
     * - `referencia_tipo`/`referencia_id` apuntan al origen del movimiento
     *   (ej. tipo='venta', id=42). NO uso morphTo por simplicidad y
     *   por mantener el join debuggeable directo desde SQL.
     * - `producto_id` se denormaliza para reportes de kardex POR PRODUCTO
     *   sin necesidad de join con lotes en cada query.
     */
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('tipo', 10);            // ENTRADA | SALIDA
            $table->string('motivo', 30);          // VENTA, ANULACION_VENTA, MERMA, etc.
            $table->unsignedInteger('cantidad');   // siempre > 0
            $table->integer('stock_anterior');     // snapshot pre-movimiento
            $table->integer('stock_posterior');    // snapshot post-movimiento
            $table->string('referencia_tipo', 30)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('observacion', 255)->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Kardex por lote: el caso de uso más frecuente.
            $table->index(['lote_id', 'created_at'], 'movinv_lote_fecha_idx');
            // Kardex por producto: para reportes agregados.
            $table->index(['producto_id', 'created_at'], 'movinv_producto_fecha_idx');
            // Reportes por motivo (ej. todas las mermas del mes).
            $table->index(['motivo', 'created_at'], 'movinv_motivo_fecha_idx');
            // Búsqueda inversa desde una referencia (ej. todos los movs de la venta X).
            $table->index(['referencia_tipo', 'referencia_id'], 'movinv_referencia_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
