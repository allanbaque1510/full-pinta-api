<?php

namespace Database\Factories;

use App\Models\CatalogoServicio;
use App\Models\Local;
use App\Models\ServicioLocal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicioLocal>
 */
class ServicioLocalFactory extends Factory
{
    protected $model = ServicioLocal::class;

    public function definition(): array
    {
        return [
            'local_id' => Local::factory(),
            'catalogo_servicio_id' => CatalogoServicio::factory(),
            'precio' => fake()->randomFloat(2, 5, 60),
            'precio_desde' => false,
            'duracion_min' => 30,
            'buffer_min' => 0,
            'comisionable' => true,
            'activo' => true,
        ];
    }

    public function duracion(int $minutos, int $buffer = 0): static
    {
        return $this->state(fn () => ['duracion_min' => $minutos, 'buffer_min' => $buffer]);
    }
}
