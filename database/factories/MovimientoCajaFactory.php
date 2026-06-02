<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoMovimientoCaja;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovimientoCaja>
 */
class MovimientoCajaFactory extends Factory
{
    protected $model = MovimientoCaja::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'caja_id'  => Caja::factory(),
            'tipo'     => TipoMovimientoCaja::EGRESO,
            'monto'    => $this->faker->randomFloat(2, 1, 200),
            'concepto' => $this->faker->sentence(3),
            'user_id'  => User::factory(),
        ];
    }
}
