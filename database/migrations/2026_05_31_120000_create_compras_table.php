<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compra (orden de compra a proveedor) con flujo de dos estados:
     *   PENDIENTE → orden registrada, NO afecta stock todavía
     *   RECIBIDA  → mercadería ingresada, se crearon lotes y kardex
     *   ANULADA   → solo posible si estaba PENDIENTE
     *
     * Separar el evento contable (registro de la orden) del evento físico
     * (recepción) refleja la realidad: entre que un proveedor te factura
     * y que el camión te entrega los productos pueden pasar días.
     */
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table): void {
            $table->id();
            $table->string('serie', 4)->default('OC');
            $table->unsignedBigInteger('numero');
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha_compra');
            $table->string('estado', 15)->default('PENDIENTE');
            $table->decimal('total', 12, 2)->default(0);
            $table->string('observaciones', 500)->nullable();

            // Recepción.
            $table->timestamp('recibida_en')->nullable();
            $table->foreignId('recibida_por')->nullable()->constrained('users')->nullOnDelete();

            // Anulación.
            $table->timestamp('anulada_en')->nullable();
            $table->foreignId('anulada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 255)->nullable();

            $table->timestamps();

            $table->unique(['serie', 'numero'], 'compras_serie_numero_uq');
            $table->index('estado');
            $table->index('proveedor_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
