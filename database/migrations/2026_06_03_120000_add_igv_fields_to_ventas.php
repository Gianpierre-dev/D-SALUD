<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Desglose tributario de la venta. El total NO cambia (en Perú el precio
     * al público ya incluye IGV); estos campos solo separan la base imponible
     * y el impuesto para que el comprobante sea válido ante SUNAT.
     *
     *   total    = subtotal (op. gravada) + igv + exonerado
     *   exonerado se deriva: total - subtotal - igv
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->decimal('subtotal', 10, 2)->default(0)->after('total'); // base imponible (gravada)
            $table->decimal('igv', 10, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropColumn(['subtotal', 'igv']);
        });
    }
};
