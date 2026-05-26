<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lote>
 */
class LoteFactory extends Factory
{
    protected $model = Lote::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'producto_id'       => Producto::factory(),
            'proveedor_id'      => Proveedor::factory(),
            'codigo_lote'       => strtoupper($this->faker->unique()->bothify('LOT-######')),
            'fecha_vencimiento' => $this->faker->dateTimeBetween('+6 months', '+3 years')->format('Y-m-d'),
            'stock'             => $this->faker->numberBetween(20, 200),
            'precio_compra'     => $this->faker->randomFloat(2, 0.50, 80.00),
        ];
    }

    /**
     * Lote con fecha de vencimiento en el futuro (no vencido).
     */
    public function vigente(): static
    {
        return $this->state([
            'fecha_vencimiento' => $this->faker->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
        ]);
    }

    /**
     * Lote ya vencido (fecha de vencimiento en el pasado).
     */
    public function vencido(): static
    {
        return $this->state([
            'fecha_vencimiento' => $this->faker->dateTimeBetween('-2 years', '-1 day')->format('Y-m-d'),
        ]);
    }

    /**
     * Lote con stock específico.
     */
    public function conStock(int $stock): static
    {
        return $this->state(['stock' => $stock]);
    }

    /**
     * Lote sin stock.
     */
    public function sinStock(): static
    {
        return $this->state(['stock' => 0]);
    }
}
