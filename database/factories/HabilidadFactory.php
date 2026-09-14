<?php

namespace Database\Factories;

use App\Models\Habilidad;
use App\Models\Profesional;
use App\Models\ServicioLocal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Habilidad>
 */
class HabilidadFactory extends Factory
{
    protected $model = Habilidad::class;

    public function definition(): array
    {
        return [
            'profesional_id' => Profesional::factory(),
            'servicio_local_id' => ServicioLocal::factory(),
            'precio_override' => null,
        ];
    }
}
