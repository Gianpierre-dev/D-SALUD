<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MotivoMovimiento;
use App\Enums\TipoMovimiento;
use App\Models\Lote;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovimientoInventario>
 */
class MovimientoInventarioFactory extends Factory
{
    protected $model = MovimientoInventario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lote_id'         => Lote::factory(),
            'producto_id'     => Producto::factory(),
            'tipo'            => TipoMovimiento::ENTRADA,
            'motivo'          => MotivoMovimiento::INVENTARIO_INICIAL,
            'cantidad'        => $this->faker->numberBetween(1, 100),
            'stock_anterior'  => 0,
            'stock_posterior' => $this->faker->numberBetween(1, 100),
            'referencia_tipo' => null,
            'referencia_id'   => null,
            'observacion'     => $this->faker->sentence(4),
            'user_id'         => User::factory(),
        ];
    }
}
