<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Línea de detalle de una compra.
     *
     * codigo_lote y fecha_vencimiento se capturan en la orden y se usan al
     * RECIBIR para crear el lote correspondiente. No se generan lotes al
     * crear la compra: el lote solo nace cuando la mercadería entra física.
     */
    public function up(): void
    {
        Schema::create('detalle_compras', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 12, 2);
            $table->string('codigo_lote', 100);
            $table->date('fecha_vencimiento');
            $table->timestamps();

            $table->index('compra_id');
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_compras');
    }
};
