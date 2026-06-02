<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoMovimientoCaja;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoCaja extends Model
{
    /** @use HasFactory<\Database\Factories\MovimientoCajaFactory> */
    use HasFactory;

    protected $table = 'movimientos_caja';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'caja_id',
        'tipo',
        'monto',
        'concepto',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo'  => TipoMovimientoCaja::class,
            'monto' => 'decimal:2',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
