<?php

namespace Database\Factories;

use App\Models\Espera;
use App\Models\Local;
use App\Models\ServicioLocal;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Espera>
 */
class EsperaFactory extends Factory
{
    protected $model = Espera::class;

    public function definition(): array
    {
        return [
            'local_id' => Local::factory(),
            'cliente_id' => Usuario::factory(),
            'profesional_id' => null,
            'servicio_local_id' => ServicioLocal::factory(),
            'fecha_deseada' => now()->addDays(3)->toDateString(),
            'desde' => '10:00',
            'hasta' => '14:00',
            'estado' => 'activa',
        ];
    }
}
