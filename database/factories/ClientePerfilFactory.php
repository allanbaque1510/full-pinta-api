<?php

namespace Database\Factories;

use App\Models\ClientePerfil;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientePerfil>
 */
class ClientePerfilFactory extends Factory
{
    protected $model = ClientePerfil::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'genero' => fake()->randomElement(['m', 'f', 'otro', 'no_decir']),
            'fecha_nacimiento' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'no_shows' => 0,
            'cancelaciones_tardias' => 0,
            'requiere_confirmacion' => false,
        ];
    }

    /** Al 3er no-show se le exige confirmar para que el slot se le reserve (§5.6). */
    public function pocoConfiable(): static
    {
        return $this->state(fn () => [
            'no_shows' => 3,
            'requiere_confirmacion' => true,
        ]);
    }
}
