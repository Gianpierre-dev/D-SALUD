<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula la venta a un cliente registrado (opcional).
     *
     * - nullable porque la venta a consumidor final no requiere cliente.
     * - nullOnDelete preserva el histórico aun si el cliente se borra.
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->foreignId('cliente_id')
                ->nullable()
                ->after('user_id')
                ->constrained('clientes')
                ->nullOnDelete();

            $table->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropForeign(['cliente_id']);
            $table->dropIndex(['cliente_id']);
            $table->dropColumn('cliente_id');
        });
    }
};
