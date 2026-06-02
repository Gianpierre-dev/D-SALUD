<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pagos de una venta. Una venta puede tener N pagos (pago mixto:
     * parte efectivo + parte Yape, por ejemplo). La suma de los montos
     * aplicados equivale al total de la venta.
     *
     * El `monto` es lo APLICADO a la venta por ese medio (no lo recibido):
     * en efectivo, lo recibido y el vuelto viven en la tabla ventas.
     * Para el cuadre de caja, el efectivo neto en gaveta = suma de montos
     * de pagos con medio EFECTIVO.
     */
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->string('medio_pago', 20); // EFECTIVO, TARJETA, YAPE, PLIN, TRANSFERENCIA
            $table->decimal('monto', 10, 2);
            $table->timestamps();

            $table->index('venta_id');
            $table->index('medio_pago');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
