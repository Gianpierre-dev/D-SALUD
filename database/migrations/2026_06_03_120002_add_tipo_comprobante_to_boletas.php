<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipo de comprobante emitido. La tabla `boletas` actúa como tabla de
     * comprobantes: BOLETA o FACTURA, diferenciados también por su serie
     * (B001 / F001). Default BOLETA para preservar los registros existentes.
     */
    public function up(): void
    {
        Schema::table('boletas', function (Blueprint $table): void {
            $table->string('tipo_comprobante', 10)->default('BOLETA')->after('venta_id');
            $table->index('tipo_comprobante');
        });
    }

    public function down(): void
    {
        Schema::table('boletas', function (Blueprint $table): void {
            $table->dropIndex(['tipo_comprobante']);
            $table->dropColumn('tipo_comprobante');
        });
    }
};
