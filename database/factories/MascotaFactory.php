<?php

namespace Database\Factories;

use App\Models\Mascota;
use App\Models\TamanoMascota;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mascota>
 */
class MascotaFactory extends Factory
{
    protected $model = Mascota::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'nombre' => fake()->firstName(),
            'especie' => 'perro',
            'raza' => fake()->randomElement(['Mestizo', 'Golden Retriever', 'Yorkshire', 'Poodle', 'Schnauzer']),
            'tamano_id' => (TamanoMascota::inRandomOrder()->first() ?? TamanoMascota::factory()->create())->id,
            'pelaje' => fake()->randomElement(['corto', 'medio', 'largo', 'rizado', 'doble_capa']),
            'peso_kg' => fake()->randomFloat(2, 2, 45),
            'temperamento' => 'tranquilo',
            'activo' => true,
        ];
    }
}
