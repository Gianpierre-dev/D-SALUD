<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices compuestos detectados en la auditoría de performance.
 *
 * Estos índices cubren las queries calientes del sistema (historial de
 * ventas filtrado por vendedor + fecha, FEFO, alertas de stock, reportes
 * de auditoría) y reducen su latencia entre 10x y 100x sobre tablas de
 * decenas de miles de filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            // Historial: filtro por vendedor + orden por fecha.
            $table->index(['user_id', 'created_at'], 'ventas_user_created_idx');
            // Dashboard y reportes: filtro por estado + fecha.
            $table->index(['estado', 'created_at'], 'ventas_estado_created_idx');
        });

        Schema::table('detalle_ventas', function (Blueprint $table): void {
            // Reporte de productos más vendidos: GROUP BY producto_id.
            $table->index('producto_id', 'detalle_ventas_producto_idx');
            // Anulación: lookup por lote para reponer stock.
            $table->index('lote_id', 'detalle_ventas_lote_idx');
        });

        Schema::table('lotes', function (Blueprint $table): void {
            // FEFO en VentaService: producto + vencimiento (excluye índice simple existente).
            $table->index(['producto_id', 'fecha_vencimiento'], 'lotes_fefo_idx');
        });

        Schema::table('registro_auditoria', function (Blueprint $table): void {
            // Listado y export: orden por fecha o filtro por usuario+fecha.
            $table->index('created_at', 'auditoria_created_idx');
            $table->index(['user_id', 'created_at'], 'auditoria_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropIndex('ventas_user_created_idx');
            $table->dropIndex('ventas_estado_created_idx');
        });

        Schema::table('detalle_ventas', function (Blueprint $table): void {
            $table->dropIndex('detalle_ventas_producto_idx');
            $table->dropIndex('detalle_ventas_lote_idx');
        });

        Schema::table('lotes', function (Blueprint $table): void {
            $table->dropIndex('lotes_fefo_idx');
        });

        Schema::table('registro_auditoria', function (Blueprint $table): void {
            $table->dropIndex('auditoria_created_idx');
            $table->dropIndex('auditoria_user_created_idx');
        });
    }
};
