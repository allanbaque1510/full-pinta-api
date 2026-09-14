<?php

namespace Database\Factories;

use App\Models\ServicioLocal;
use App\Models\ServicioLocalTamano;
use App\Models\TamanoMascota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicioLocalTamano>
 */
class ServicioLocalTamanoFactory extends Factory
{
    protected $model = ServicioLocalTamano::class;

    public function definition(): array
    {
        return [
            'servicio_local_id' => ServicioLocal::factory(),
            'tamano_id' => (TamanoMascota::where('codigo', 'mediano')->first()
                ?? TamanoMascota::factory()->create(['codigo' => 'mediano']))->id,
            'precio' => fake()->randomFloat(2, 10, 50),
            'duracion_min' => 45,
        ];
    }
}
