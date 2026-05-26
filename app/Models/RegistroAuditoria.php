<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroAuditoria extends Model
{
    use HasFactory;

    protected $table = 'registro_auditoria';

    protected $fillable = [
        'user_id',
        'modulo',
        'accion',
        'ip',
        'detalle',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
