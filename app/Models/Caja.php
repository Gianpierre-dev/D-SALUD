<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoCaja;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Caja extends Model
{
    /** @use HasFactory<\Database\Factories\CajaFactory> */
    use HasFactory;

    protected $table = 'cajas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'abierta_en',
        'monto_apertura',
        'cerrada_en',
        'cerrada_por',
        'monto_cierre',
        'total_ventas',
        'total_esperado',
        'diferencia',
        'estado',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abierta_en'     => 'datetime',
            'cerrada_en'     => 'datetime',
            'monto_apertura' => 'decimal:2',
            'monto_cierre'   => 'decimal:2',
            'total_ventas'   => 'decimal:2',
            'total_esperado' => 'decimal:2',
            'diferencia'     => 'decimal:2',
            'estado'         => EstadoCaja::class,
        ];
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    public function scopeAbierta($query)
    {
        return $query->where('estado', EstadoCaja::ABIERTA->value);
    }
}
