<?php

namespace Database\Factories;

use App\Models\HorarioLocal;
use App\Models\Local;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HorarioLocal>
 */
class HorarioLocalFactory extends Factory
{
    protected $model = HorarioLocal::class;

    public function definition(): array
    {
        return [
            'local_id' => Local::factory(),
            'dia_semana' => fake()->numberBetween(1, 6),
            'abre' => '09:00',
            'cierra' => '19:00',
        ];
    }
}
