<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MotivoMovimiento;
use App\Enums\TipoMovimiento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento de inventario (kardex).
 * Inmutable por contrato: no se actualiza ni se elimina.
 * Cualquier corrección se hace con un movimiento compensatorio nuevo.
 */
class MovimientoInventario extends Model
{
    /** @use HasFactory<\Database\Factories\MovimientoInventarioFactory> */
    use HasFactory;

    protected $table = 'movimientos_inventario';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lote_id',
        'producto_id',
        'tipo',
        'motivo',
        'cantidad',
        'stock_anterior',
        'stock_posterior',
        'referencia_tipo',
        'referencia_id',
        'observacion',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo'   => TipoMovimiento::class,
            'motivo' => MotivoMovimiento::class,
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
