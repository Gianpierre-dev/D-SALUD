<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoDocumento;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Por defecto DNI; los tests de RUC usan el state ::ruc().
        return [
            'tipo_documento'   => TipoDocumento::DNI,
            'numero_documento' => (string) $this->faker->numerify('########'),
            'nombre'           => $this->faker->name(),
            'telefono'         => $this->faker->numerify('9########'),
            'email'            => $this->faker->safeEmail(),
            'direccion'        => $this->faker->streetAddress(),
            'activo'           => true,
        ];
    }

    public function ruc(): self
    {
        return $this->state(fn () => [
            'tipo_documento'   => TipoDocumento::RUC,
            'numero_documento' => '20' . $this->faker->numerify('#########'),
            'nombre'           => $this->faker->company(),
        ]);
    }

    public function inactivo(): self
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
