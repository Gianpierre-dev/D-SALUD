<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    /** @use HasFactory<\Database\Factories\ClienteFactory> */
    use HasFactory;

    protected $table = 'clientes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'nombre',
        'telefono',
        'email',
        'direccion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
            'activo'         => 'boolean',
        ];
    }

    /**
     * Ventas asociadas a este cliente (histórico).
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }
}
