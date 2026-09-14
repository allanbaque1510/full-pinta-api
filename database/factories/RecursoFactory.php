<?php

namespace Database\Factories;

use App\Models\Local;
use App\Models\Recurso;
use App\Models\TipoRecurso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recurso>
 *
 * Una fila por unidad física: "Silla 3", "Mesa 1". Nunca una fila con cantidad.
 */
class RecursoFactory extends Factory
{
    protected $model = Recurso::class;

    public function definition(): array
    {
        return [
            'local_id' => Local::factory(),
            'tipo_recurso_id' => self::tipoRecursoId('silla'),
            'nombre' => 'Silla '.fake()->numberBetween(1, 8),
            'activo' => true,
        ];
    }

    public function tipo(string $tipo, ?string $nombre = null): static
    {
        return $this->state(fn () => [
            'tipo_recurso_id' => self::tipoRecursoId($tipo),
            'nombre' => $nombre ?? ucfirst(str_replace('_', ' ', $tipo)),
        ]);
    }

    private static function tipoRecursoId(string $codigo): string
    {
        return (TipoRecurso::where('codigo', $codigo)->first() ?? TipoRecurso::factory()->create(['codigo' => $codigo]))->id;
    }
}
