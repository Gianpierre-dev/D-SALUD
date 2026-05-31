<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cajas: cada turno operativo del POS abre una caja con monto inicial
     * y la cierra al final con monto declarado. El sistema calcula el total
     * esperado (apertura + ventas del periodo) y la diferencia con el
     * declarado. La diferencia puede ser sobrante (positiva) o faltante
     * (negativa) y queda como evidencia de auditoría.
     *
     * Un mismo usuario solo puede tener UNA caja ABIERTA a la vez
     * (validado en service + índice parcial sobre estado='ABIERTA').
     */
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('abierta_en');
            $table->decimal('monto_apertura', 10, 2);

            // Cierre (nullable mientras esté ABIERTA).
            $table->timestamp('cerrada_en')->nullable();
            $table->foreignId('cerrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('monto_cierre', 10, 2)->nullable();        // contado físico
            $table->decimal('total_ventas', 12, 2)->nullable();        // sum ventas COMPLETADAS del periodo
            $table->decimal('total_esperado', 12, 2)->nullable();      // apertura + ventas
            $table->decimal('diferencia', 12, 2)->nullable();          // declarado - esperado

            $table->string('estado', 10)->default('ABIERTA');
            $table->string('observaciones', 500)->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('estado');
            $table->index('abierta_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
