<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoCaja;
use App\Models\Caja;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Caja>
 */
class CajaFactory extends Factory
{
    protected $model = Caja::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'abierta_en'     => now()->subHours(2),
            'monto_apertura' => 100.00,
            'estado'         => EstadoCaja::ABIERTA,
        ];
    }

    public function cerrada(): self
    {
        return $this->state(fn () => [
            'estado'         => EstadoCaja::CERRADA,
            'cerrada_en'     => now(),
            'monto_cierre'   => 250.00,
            'total_ventas'   => 150.00,
            'total_esperado' => 250.00,
            'diferencia'     => 0,
        ]);
    }
}
