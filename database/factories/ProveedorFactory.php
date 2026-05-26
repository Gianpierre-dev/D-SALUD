<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $distribuidoras = [
            'Distribuidora Farma SAC',
            'Laboratorios Farmindustria SA',
            'Medifarma SAC',
            'Hersil SA',
            'Droguería Universal SAC',
            'InkaBiopharma SA',
            'AC Farma SA',
            'Albis SA',
            'Medrock SAC',
            'Novartis Biociencias SA',
        ];

        return [
            'ruc'          => $this->faker->unique()->numerify('20#########'),
            'razon_social' => $this->faker->unique()->randomElement($distribuidoras),
            'contacto'     => $this->faker->name(),
            'telefono'     => $this->faker->optional(0.8)->numerify('9########'),
            'email'        => $this->faker->optional(0.7)->companyEmail(),
            'direccion'    => $this->faker->optional(0.6)->address(),
            'activo'       => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }
}
