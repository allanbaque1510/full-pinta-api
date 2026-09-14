<?php

namespace Database\Factories;

use App\Models\Favorito;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Favorito>
 *
 * Un favorito es de un local o de un profesional, nunca de ambos.
 */
class FavoritoFactory extends Factory
{
    protected $model = Favorito::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'local_id' => Local::factory(),
            'profesional_id' => null,
        ];
    }

    public function deProfesional(): static
    {
        return $this->state(fn () => [
            'local_id' => null,
            'profesional_id' => Profesional::factory(),
        ]);
    }
}
