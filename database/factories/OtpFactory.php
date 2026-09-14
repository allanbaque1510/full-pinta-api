<?php

namespace Database\Factories;

use App\Models\Otp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Otp>
 */
class OtpFactory extends Factory
{
    protected $model = Otp::class;

    public function definition(): array
    {
        return [
            'telefono' => '09'.fake()->numerify('########'),
            'codigo_hash' => Hash::make('123456'),
            'intentos' => 0,
            'expira_at' => now()->addMinutes(5),
            'created_at' => now(),
        ];
    }

    public function expirado(): static
    {
        return $this->state(fn () => ['expira_at' => now()->subMinute()]);
    }

    public function verificado(): static
    {
        return $this->state(fn () => ['verificado_at' => now()]);
    }

    public function conCodigo(string $codigo): static
    {
        return $this->state(fn () => ['codigo_hash' => Hash::make($codigo)]);
    }
}
