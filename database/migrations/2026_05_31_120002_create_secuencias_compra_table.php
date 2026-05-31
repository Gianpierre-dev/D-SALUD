<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Numeración correlativa atómica por serie de orden de compra.
     * Mismo patrón que secuencias_boleta: la fila se bloquea con
     * lockForUpdate antes de incrementar para serializar accesos.
     */
    public function up(): void
    {
        Schema::create('secuencias_compra', function (Blueprint $table): void {
            $table->string('serie', 4)->primary();
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secuencias_compra');
    }
};
