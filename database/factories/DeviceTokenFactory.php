<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceToken>
 */
class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'token' => fake()->unique()->sha256(),
            'plataforma' => fake()->randomElement(['android', 'ios']),
            'app_version' => '1.0.0',
            'activo' => true,
            'ultimo_uso_at' => now(),
        ];
    }
}
