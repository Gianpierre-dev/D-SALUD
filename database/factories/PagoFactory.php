<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MedioPago;
use App\Models\Pago;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    protected $model = Pago::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venta_id'   => Venta::factory(),
            'medio_pago' => MedioPago::EFECTIVO,
            'monto'      => $this->faker->randomFloat(2, 1, 500),
        ];
    }
}
