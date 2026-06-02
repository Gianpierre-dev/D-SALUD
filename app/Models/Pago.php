<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MedioPago;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    /** @use HasFactory<\Database\Factories\PagoFactory> */
    use HasFactory;

    protected $table = 'pagos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'venta_id',
        'medio_pago',
        'monto',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'medio_pago' => MedioPago::class,
            'monto'      => 'decimal:2',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }
}
