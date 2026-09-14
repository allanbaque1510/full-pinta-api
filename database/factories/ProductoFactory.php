<?php

namespace Database\Factories;

use App\Models\Local;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    public function definition(): array
    {
        return [
            'local_id' => Local::factory(),
            'nombre' => fake()->randomElement(['Pomada', 'Cera', 'Shampoo', 'Aceite de barba']),
            'precio' => fake()->randomFloat(2, 3, 25),
            'comision_pct' => 10,
            'activo' => true,
        ];
    }
}
