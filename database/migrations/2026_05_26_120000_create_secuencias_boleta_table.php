<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de secuencias para los correlativos de boletas.
     *
     * Elimina el race condition que existía al calcular el siguiente número con
     * `MAX(numero) + 1` sobre la tabla `boletas`: dos transacciones concurrentes
     * podían leer el mismo MAX y la segunda fallaría con duplicado al insertar.
     * Con esta tabla, el avance del correlativo se hace con `lockForUpdate` sobre
     * la fila de la serie, serializando el acceso a nivel de fila.
     */
    public function up(): void
    {
        Schema::create('secuencias_boleta', function (Blueprint $table) {
            $table->string('serie', 10)->primary();
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });

        // Pre-siembra la serie configurada para evitar la primera lectura
        // de un valor nulo en producción.
        DB::table('secuencias_boleta')->insert([
            'serie' => config('dsalud.boleta.serie'),
            'ultimo_numero' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('secuencias_boleta');
    }
};
