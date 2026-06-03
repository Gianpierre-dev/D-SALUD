<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoComprobante;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Boleta extends Model
{
    use HasFactory;

    protected $table = 'boletas';

    protected $fillable = [
        'venta_id',
        'tipo_comprobante',
        'serie',
        'numero',
        'fecha_emision',
    ];

    protected $casts = [
        'tipo_comprobante' => TipoComprobante::class,
        'numero' => 'integer',
        'fecha_emision' => 'datetime',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * Número de boleta formateado (ej. B001-00000123).
     */
    public function getNumeroFormateadoAttribute(): string
    {
        return sprintf('%s-%08d', $this->serie, $this->numero);
    }
}
