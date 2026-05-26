<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->string('laboratorio')->nullable();
            $table->string('unidad_medida')->default('unidad');
            $table->decimal('precio_venta', 10, 2);
            $table->unsignedInteger('stock_minimo')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
