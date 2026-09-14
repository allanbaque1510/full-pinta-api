<?php

namespace Database\Factories;

use App\Models\ClienteLocal;
use App\Models\Local;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClienteLocal>
 */
class ClienteLocalFactory extends Factory
{
    protected $model = ClienteLocal::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'local_id' => Local::factory(),
            'primera_cita_at' => now()->subMonths(3),
            'ultima_cita_at' => now()->subWeek(),
            'total_citas' => fake()->numberBetween(1, 20),
            'nota' => fake()->optional()->sentence(4),
        ];
    }
}
