<?php

namespace Database\Factories;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Usuario>
 *
 * Por defecto, un cliente registrado. El estado `sombra` reproduce al walk-in
 * que la recepción creó con solo nombre y teléfono (§4.3).
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    public function definition(): array
    {
        return [
            'telefono' => '09'.fake()->unique()->numerify('########'),
            'telefono_verificado' => true,
            'email' => fake()->unique()->safeEmail(),
            'nombre' => fake()->name(),
            'password_hash' => bcrypt('secreta'),
        ];
    }

    /** Cliente sombra: sin contraseña, creado por la recepción para un walk-in. */
    public function sombra(): static
    {
        return $this->state(fn () => [
            'password_hash' => null,
            'telefono_verificado' => false,
            'email' => null,
        ]);
    }

    /** LOPDP: derecho de eliminación ejercido (§13.1). */
    public function anonimizado(): static
    {
        return $this->state(fn () => ['anonimizado_at' => now()]);
    }
}
