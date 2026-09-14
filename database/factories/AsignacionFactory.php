<?php

namespace Database\Factories;

use App\Models\Asignacion;
use App\Models\Local;
use App\Models\Profesional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asignacion>
 */
class AsignacionFactory extends Factory
{
    protected $model = Asignacion::class;

    public function definition(): array
    {
        return [
            'local_id' => Local::factory(),
            'profesional_id' => Profesional::factory(),
            'rol' => 'barbero',
            'modalidad' => 'empleado',
            'comision_pct' => 50,
            'desde' => now()->subMonth()->toDateString(),
        ];
    }

    public function terminada(): static
    {
        return $this->state(fn () => ['hasta' => now()->subDay()->toDateString()]);
    }
}
