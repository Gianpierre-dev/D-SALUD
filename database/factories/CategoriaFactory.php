<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $categorias = [
            'Analgésicos',
            'Antibióticos',
            'Antiinflamatorios',
            'Vitaminas y Suplementos',
            'Antihipertensivos',
            'Antiácidos',
            'Antidiabéticos',
            'Antihistamínicos',
            'Antiparasitarios',
            'Dermatológicos',
        ];

        return [
            'nombre'      => $this->faker->unique()->randomElement($categorias),
            'descripcion' => $this->faker->optional(0.7)->sentence(),
            'activo'      => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(['activo' => false]);
    }
}
