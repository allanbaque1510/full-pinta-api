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
            'nota' => fake()->optional()->sentence(4),
        ];
    }
}
