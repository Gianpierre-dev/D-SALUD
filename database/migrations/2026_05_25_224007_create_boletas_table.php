<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boletas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->unique()->constrained('ventas')->cascadeOnDelete();
            $table->string('serie', 10)->default('B001');
            $table->unsignedInteger('numero');
            $table->timestamp('fecha_emision');
            $table->timestamps();

            $table->unique(['serie', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boletas');
    }
};
