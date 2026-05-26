<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'productos';

    protected $fillable = [
        'codigo',
        'nombre',
        'categoria_id',
        'laboratorio',
        'unidad_medida',
        'precio_venta',
        'stock_minimo',
        'activo',
    ];

    protected $casts = [
        'precio_venta' => 'decimal:2',
        'stock_minimo' => 'integer',
        'activo' => 'boolean',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }

    /**
     * Stock total del producto: suma del stock de todos sus lotes.
     */
    public function stockTotal(): int
    {
        return (int) $this->lotes()->sum('stock');
    }
}
