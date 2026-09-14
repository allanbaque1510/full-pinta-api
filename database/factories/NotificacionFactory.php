<?php

namespace Database\Factories;

use App\Models\Notificacion;
use App\Models\NotificacionCategoria;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notificacion>
 */
class NotificacionFactory extends Factory
{
    protected $model = Notificacion::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'tipo_evento' => 'cita_creada',
            'categoria_id' => (NotificacionCategoria::where('codigo', 'citas')->first()
                ?? NotificacionCategoria::factory()->create(['codigo' => 'citas']))->id,
            'canal' => 'push',
            'estado' => 'programada',
            'programada_para' => now(),
            'costo_usd' => 0,
        ];
    }

    public function whatsapp(): static
    {
        return $this->state(fn () => ['canal' => 'whatsapp', 'costo_usd' => 0.025]);
    }
}
