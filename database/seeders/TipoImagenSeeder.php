<?php

namespace Database\Seeders;

use App\Models\TipoImagen;
use Illuminate\Database\Seeder;

/**
 * Tipos de imagen (§4.4): compartido por la galería polimórfica `imagen` de
 * `local`, `profesional`, `mascota`, `usuario`...
 */
class TipoImagenSeeder extends Seeder
{
    /**
     * codigo => nombre, en orden de presentación.
     *
     * @var array<string, string>
     */
    private const TIPOS = [
        'perfil' => 'Foto de perfil',
        'portada' => 'Foto de portada',
        'fachada' => 'Fachada',
        'interior' => 'Interior',
        'muestra' => 'Muestra de trabajo',
    ];

    public function run(): void
    {
        $orden = 0;

        foreach (self::TIPOS as $codigo => $nombre) {
            TipoImagen::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'orden' => ++$orden, 'activo' => true],
            );
        }
    }
}
