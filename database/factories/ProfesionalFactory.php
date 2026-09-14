<?php

namespace Database\Factories;

use App\Models\Profesional;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profesional>
 */
class ProfesionalFactory extends Factory
{
    protected $model = Profesional::class;

    public function definition(): array
    {
        return [
            'usuario_id' => null,
            'nombre' => fake()->name(),
            'alias' => fake()->optional()->firstName(),
            'bio' => fake()->optional()->sentence(),
            'independiente' => false,
            'perfil_publico' => true,
            'traslado_min' => 30,
        ];
    }

    /** Con cuenta en la app: puede ver su propia agenda y comisiones. */
    public function conCuenta(): static
    {
        return $this->state(fn () => ['usuario_id' => Usuario::factory()]);
    }
}
