<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoCompra;
use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Compra>
 */
class CompraFactory extends Factory
{
    protected $model = Compra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'serie'         => 'OC',
            'numero'        => $this->faker->unique()->numberBetween(1, 99999),
            'proveedor_id'  => Proveedor::factory(),
            'user_id'       => User::factory(),
            'fecha_compra'  => now()->toDateString(),
            'estado'        => EstadoCompra::PENDIENTE,
            'total'         => 0,
            'observaciones' => null,
        ];
    }

    public function recibida(): self
    {
        return $this->state(fn () => [
            'estado'      => EstadoCompra::RECIBIDA,
            'recibida_en' => now(),
        ]);
    }

    public function anulada(): self
    {
        return $this->state(fn () => [
            'estado'           => EstadoCompra::ANULADA,
            'anulada_en'       => now(),
            'motivo_anulacion' => 'Anulada por test',
        ]);
    }
}
