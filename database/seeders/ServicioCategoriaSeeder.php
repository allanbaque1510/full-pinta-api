<?php

namespace Database\Seeders;

use App\Models\Rubro;
use App\Models\ServicioCategoria;
use Illuminate\Database\Seeder;

/**
 * Categorías del catálogo maestro (§4.5).
 *
 * El mismo código existe en rubros distintos —`corte` es categoría de
 * barbería y también de estética— y por eso `codigo` es único junto a
 * `rubro_id`, no por sí solo.
 */
class ServicioCategoriaSeeder extends Seeder
{
    /**
     * rubro => [codigo => [nombre, icono]], en orden de presentación.
     *
     * @var array<string, array<string, array{string, string}>>
     */
    private const CATEGORIAS = [
        'barberia' => [
            'corte' => ['Cortes', 'scissors'],
            'barba' => ['Barba', 'beard'],
            'cejas' => ['Cejas', 'eye'],
            'tratamiento' => ['Tratamientos', 'sparkles'],
            'color' => ['Color', 'palette'],
        ],
        'estetica' => [
            'corte' => ['Cortes', 'scissors'],
            'peinado' => ['Peinados', 'wind'],
            'color' => ['Color', 'palette'],
            'tratamiento' => ['Tratamientos', 'sparkles'],
            'depilacion' => ['Depilación', 'feather'],
        ],
        'unas' => [
            'manicura' => ['Manicura', 'hand'],
            'pedicura' => ['Pedicura', 'footprints'],
            'esmaltado' => ['Esmaltado', 'brush'],
            'extension' => ['Extensiones', 'plus'],
        ],
        'mascotas' => [
            'bano' => ['Baño', 'droplets'],
            'corte' => ['Corte', 'scissors'],
            'higiene' => ['Higiene', 'heart-pulse'],
        ],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIAS as $rubro => $categorias) {
            $rubroId = Rubro::where('codigo', $rubro)->value('id');
            $orden = 0;

            foreach ($categorias as $codigo => [$nombre, $icono]) {
                ServicioCategoria::updateOrCreate(
                    ['rubro_id' => $rubroId, 'codigo' => $codigo],
                    ['nombre' => $nombre, 'icono' => $icono, 'orden' => ++$orden, 'activo' => true],
                );
            }
        }
    }
}
