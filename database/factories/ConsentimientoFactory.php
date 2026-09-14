<?php

namespace Database\Factories;

use App\Models\Consentimiento;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consentimiento>
 */
class ConsentimientoFactory extends Factory
{
    protected $model = Consentimiento::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'finalidad' => 'operacion_servicio',
            'documento_version' => '2026-01',
            'otorgado' => true,
            'origen' => 'app',
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'otorgado_at' => now(),
        ];
    }

    public function revocado(): static
    {
        return $this->state(fn () => ['revocado_at' => now()]);
    }
}
