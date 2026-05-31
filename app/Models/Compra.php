<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoCompra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    /** @use HasFactory<\Database\Factories\CompraFactory> */
    use HasFactory;

    protected $table = 'compras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'serie',
        'numero',
        'proveedor_id',
        'user_id',
        'fecha_compra',
        'estado',
        'total',
        'observaciones',
        'recibida_en',
        'recibida_por',
        'anulada_en',
        'anulada_por',
        'motivo_anulacion',
    ];

    /**
     * Atributos calculados que SÍ deben aparecer en la serialización JSON.
     * `numero_formateado` se usa en toda la UI; sin appends el front
     * recibiría `serie` y `numero` por separado.
     *
     * @var list<string>
     */
    protected $appends = ['numero_formateado'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado'        => EstadoCompra::class,
            'fecha_compra'  => 'date',
            'total'         => 'decimal:2',
            'recibida_en'   => 'datetime',
            'anulada_en'    => 'datetime',
        ];
    }

    /**
     * Número formateado del documento, ej. "OC-00001".
     */
    public function getNumeroFormateadoAttribute(): string
    {
        return sprintf('%s-%05d', $this->serie, $this->numero);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recibidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibida_por');
    }

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleCompra::class);
    }
}
