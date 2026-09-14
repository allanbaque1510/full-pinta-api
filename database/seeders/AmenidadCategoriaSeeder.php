<?php

namespace Database\Seeders;

use App\Models\AmenidadCategoria;
use Illuminate\Database\Seeder;

/**
 * Categorías de amenidad (§4.4): confort, entretenimiento, niños,
 * accesibilidad, pago, política.
 */
class AmenidadCategoriaSeeder extends Seeder
{
    /**
     * codigo => nombre, en orden de presentación.
     *
     * @var array<string, string>
     */
    private const CATEGORIAS = [
        'confort' => 'Confort',
        'entretenimiento' => 'Entretenimiento',
        'ninos' => 'Niños',
        'accesibilidad' => 'Accesibilidad',
        'pago' => 'Pago',
        'politica' => 'Política',
    ];

    public function run(): void
    {
        $orden = 0;

        foreach (self::CATEGORIAS as $codigo => $nombre) {
            AmenidadCategoria::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'orden' => ++$orden, 'activo' => true],
            );
        }
    }
}
