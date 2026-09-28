<?php

namespace Database\Factories;

use App\Models\Imagen;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\TipoImagen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Imagen>
 */
class ImagenFactory extends Factory
{
    protected $model = Imagen::class;

    public function definition(): array
    {
        return [
            'objeto_type' => 'local',
            'objeto_id' => Local::factory(),
            'tipo_id' => (TipoImagen::where('codigo', 'muestra')->first()
                ?? TipoImagen::factory()->create(['codigo' => 'muestra', 'nombre' => 'Muestra']))->id,
            'url' => fake()->imageUrl(),
            'orden' => 0,
            'created_at' => now(),
        ];
    }

    public function paraLocal(Local $local): static
    {
        return $this->state(fn () => ['objeto_type' => 'local', 'objeto_id' => $local->id]);
    }

    public function paraProfesional(Profesional $profesional): static
    {
        return $this->state(fn () => ['objeto_type' => 'profesional', 'objeto_id' => $profesional->id]);
    }
}
