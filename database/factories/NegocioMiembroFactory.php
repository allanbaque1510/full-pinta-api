<?php

namespace Database\Factories;

use App\Models\Negocio;
use App\Models\NegocioMiembro;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NegocioMiembro>
 */
class NegocioMiembroFactory extends Factory
{
    protected $model = NegocioMiembro::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'negocio_id' => Negocio::factory(),
            'local_id' => null,
            'rol' => 'recepcion',
            'desde' => now()->subMonth()->toDateString(),
        ];
    }

    public function propietario(): static
    {
        return $this->state(fn () => ['rol' => 'propietario']);
    }

    public function recepcion(): static
    {
        return $this->state(fn () => ['rol' => 'recepcion']);
    }
}
