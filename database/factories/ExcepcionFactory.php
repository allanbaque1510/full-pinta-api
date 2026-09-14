<?php

namespace Database\Factories;

use App\Models\Excepcion;
use App\Models\Local;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Excepcion>
 */
class ExcepcionFactory extends Factory
{
    protected $model = Excepcion::class;

    public function definition(): array
    {
        return [
            'local_id' => Local::factory(),
            'profesional_id' => null,
            'recurso_id' => null,
            'fecha_inicio' => now()->addDay()->startOfDay(),
            'fecha_fin' => now()->addDay()->endOfDay(),
            'motivo' => 'feriado',
        ];
    }

    /**
     * Ausencia del profesional: con `local_id` en NULL lo bloquea en TODOS sus
     * locales — no está enfermo solo en una sucursal (§4.6).
     */
    public function deProfesional(string $profesionalId): static
    {
        return $this->state(fn () => ['local_id' => null, 'profesional_id' => $profesionalId, 'motivo' => 'personal']);
    }

    public function deRecurso(string $recursoId): static
    {
        return $this->state(fn () => ['local_id' => null, 'recurso_id' => $recursoId, 'motivo' => 'mantenimiento']);
    }
}
