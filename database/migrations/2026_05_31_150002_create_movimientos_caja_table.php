<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Movimientos de efectivo de la caja que NO son ventas:
     *   INGRESO  → entrada de efectivo (ej. fondo adicional)
     *   EGRESO   → salida de efectivo (retiro, pago a proveedor, gasto menor)
     *
     * Afectan el cuadre: el efectivo esperado en gaveta suma INGRESOS y
     * resta EGRESOS además de las ventas en efectivo. Sin esto, sacar
     * dinero de la caja producía un "faltante" falso al cerrar.
     */
    public function up(): void
    {
        Schema::create('movimientos_caja', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->string('tipo', 10); // INGRESO | EGRESO
            $table->decimal('monto', 10, 2);
            $table->string('concepto', 255);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['caja_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};
