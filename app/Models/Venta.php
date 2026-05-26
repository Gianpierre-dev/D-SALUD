<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Venta extends Model
{
    use HasFactory;

    protected $table = 'ventas';

    public const ESTADO_COMPLETADA = 'COMPLETADA';
    public const ESTADO_ANULADA = 'ANULADA';

    protected $fillable = [
        'user_id',
        'total',
        'estado',
        'motivo_anulacion',
        'anulada_por',
        'anulada_en',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'anulada_en' => 'datetime',
    ];

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class);
    }

    public function boleta(): HasOne
    {
        return $this->hasOne(Boleta::class);
    }
}
