<?php

namespace Database\Factories;

use App\Models\Asignacion;
use App\Models\Turno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Turno>
 *
 * `profesional_id` y `local_id` se copian de la asignación: están
 * desnormalizados solo para habilitar el constraint `turno_sin_traslape` (§4.11)
 * y tienen que coincidir, o el constraint protege lo que no es.
 */
class TurnoFactory extends Factory
{
    protected $model = Turno::class;

    public function definition(): array
    {
        return [
            'dia_semana' => 1,
            'entra' => '09:00',
            'sale' => '18:00',
            'vigente_desde' => now()->subMonth()->toDateString(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function ($turno) {
            if ($turno->asignacion_id === null) {
                $turno->asignacion_id = Asignacion::factory()->create()->id;
            }

            $asignacion = Asignacion::findOrFail($turno->asignacion_id);

            $turno->profesional_id = $asignacion->profesional_id;
            $turno->local_id = $asignacion->local_id;
        });
    }

    public function para(Asignacion $asignacion): static
    {
        return $this->state(fn () => ['asignacion_id' => $asignacion->id]);
    }

    public function horario(string $entra, string $sale, int $diaSemana = 1): static
    {
        return $this->state(fn () => ['entra' => $entra, 'sale' => $sale, 'dia_semana' => $diaSemana]);
    }
}
