<?php

namespace Database\Factories;

use App\Models\Cita;
use App\Models\CitaProducto;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CitaProducto>
 */
class CitaProductoFactory extends Factory
{
    protected $model = CitaProducto::class;

    public function definition(): array
    {
        return [
            'cita_id' => Cita::factory(),
            'producto_id' => Producto::factory(),
            'cantidad' => 1,
            'precio' => fake()->randomFloat(2, 3, 25),
            'comision_pct' => 10,
        ];
    }
}
