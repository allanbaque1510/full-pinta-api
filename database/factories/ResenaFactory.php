<?php

namespace Database\Factories;

use App\Models\Cita;
use App\Models\Resena;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resena>
 *
 * Se cuelga de una cita completada; local y profesional se copian de ella para
 * que las consultas de ranking no tengan que hacer join (§4.8).
 */
class ResenaFactory extends Factory
{
    protected $model = Resena::class;

    public function definition(): array
    {
        return [
            'puntaje_local' => fake()->numberBetween(3, 5),
            'puntaje_profesional' => fake()->numberBetween(3, 5),
            'puntualidad' => fake()->numberBetween(3, 5),
            'limpieza' => fake()->numberBetween(3, 5),
            'comentario' => fake()->optional()->sentence(),
            'estado' => 'publicada',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function ($resena) {
            if ($resena->cita_id === null) {
                $resena->cita_id = Cita::factory()->completada()->create()->id;
            }

            $cita = Cita::findOrFail($resena->cita_id);

            $resena->local_id = $cita->local_id;
            $resena->profesional_id = $cita->profesional_id;
        });
    }
}
