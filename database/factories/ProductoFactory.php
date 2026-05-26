<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $medicamentos = [
            'Paracetamol 500mg',
            'Ibuprofeno 400mg',
            'Amoxicilina 500mg',
            'Omeprazol 20mg',
            'Metformina 850mg',
            'Atorvastatina 20mg',
            'Losartan 50mg',
            'Clonazepam 0.5mg',
            'Azitromicina 500mg',
            'Complejo B',
            'Vitamina C 1000mg',
            'Diclofenaco 50mg',
            'Ranitidina 150mg',
            'Ciprofloxacino 500mg',
            'Loratadina 10mg',
        ];

        $unidades = ['tableta', 'cápsula', 'frasco', 'ampolla', 'sobre', 'unidad'];

        return [
            'codigo'       => strtoupper($this->faker->unique()->bothify('MED-####')),
            'nombre'       => $this->faker->unique()->randomElement($medicamentos),
            'categoria_id' => Categoria::factory(),
            'laboratorio'  => $this->faker->optional(0.8)->company(),
            'unidad_medida' => $this->faker->randomElement($unidades),
            'precio_venta' => $this->faker->randomFloat(2, 1.50, 150.00),
            'stock_minimo' => $this->faker->numberBetween(5, 20),
            'activo'       => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }

    public function conCategoria(Categoria $categoria): static
    {
        return $this->state(['categoria_id' => $categoria->id]);
    }
}
