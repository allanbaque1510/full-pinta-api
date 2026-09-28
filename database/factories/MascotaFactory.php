<?php

namespace Database\Factories;

use App\Models\EspecieMascota;
use App\Models\Mascota;
use App\Models\RazaMascota;
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
        $especie = EspecieMascota::inRandomOrder()->first() ?? EspecieMascota::factory()->create(['codigo' => 'perro', 'nombre' => 'Perro']);

        return [
            'usuario_id' => Usuario::factory(),
            'nombre' => fake()->firstName(),
            'especie_id' => $especie->id,
            'raza_id' => (RazaMascota::where('especie_id', $especie->id)->inRandomOrder()->first()
                ?? RazaMascota::factory()->create(['especie_id' => $especie->id, 'codigo' => 'mestizo', 'nombre' => 'Mestizo']))->id,
            'tamano_id' => (TamanoMascota::inRandomOrder()->first() ?? TamanoMascota::factory()->create())->id,
            'pelaje' => fake()->randomElement(['corto', 'medio', 'largo', 'rizado', 'doble_capa']),
            'peso_kg' => fake()->randomFloat(2, 2, 45),
            'temperamento' => 'tranquilo',
            'activo' => true,
        ];
    }
}
