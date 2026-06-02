<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula la venta a la caja del turno y captura el efectivo recibido/vuelto.
     *
     * - caja_id: FK directa a la caja abierta al momento de la venta. Convierte
     *   el cuadre de "inferido por rango de fecha" a "vínculo real": el reporte Z
     *   suma exactamente las ventas de ESA caja.
     * - monto_recibido / vuelto: solo aplican al componente EFECTIVO. Informan
     *   cuánto entregó el cliente y cuánto se le devolvió.
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->foreignId('caja_id')
                ->nullable()
                ->after('cliente_id')
                ->constrained('cajas')
                ->nullOnDelete();

            $table->decimal('monto_recibido', 10, 2)->nullable()->after('total');
            $table->decimal('vuelto', 10, 2)->nullable()->after('monto_recibido');

            $table->index('caja_id');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropForeign(['caja_id']);
            $table->dropIndex(['caja_id']);
            $table->dropColumn(['caja_id', 'monto_recibido', 'vuelto']);
        });
    }
};
