<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca si el producto está afecto al IGV. La mayoría de productos de
     * botica están gravados; algunos medicamentos (oncológicos, diabetes,
     * según norma) están exonerados. Default true (gravado).
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->boolean('afecto_igv')->default(true)->after('precio_venta');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->dropColumn('afecto_igv');
        });
    }
};
