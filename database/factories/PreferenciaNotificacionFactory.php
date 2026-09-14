<?php

namespace Database\Factories;

use App\Models\NotificacionCategoria;
use App\Models\PreferenciaNotificacion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreferenciaNotificacion>
 */
class PreferenciaNotificacionFactory extends Factory
{
    protected $model = PreferenciaNotificacion::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'categoria_id' => (NotificacionCategoria::where('codigo', 'citas')->first()
                ?? NotificacionCategoria::factory()->create(['codigo' => 'citas']))->id,
            'push' => true,
            'whatsapp' => true,
        ];
    }
}
