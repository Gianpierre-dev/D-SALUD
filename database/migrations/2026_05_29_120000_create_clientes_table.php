<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table): void {
            $table->id();
            // Tipo y número de documento: se valida vía Enum + FormRequest.
            // No uso ENUM nativo de SQL para mantener portabilidad sqlite/mysql.
            $table->string('tipo_documento', 4);
            $table->string('numero_documento', 15)->unique();
            $table->string('nombre', 255);
            $table->string('telefono', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('tipo_documento');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
