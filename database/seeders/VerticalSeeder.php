<?php

namespace Database\Seeders;

use App\Models\Vertical;
use Illuminate\Database\Seeder;

/**
 * Verticales del catálogo maestro (§4.5): barbería, estética, uñas, mascotas.
 *
 * Son un campo del catálogo, no tipos distintos de local — un local puede
 * ofrecer corte de caballero y uñas sin dos modelos paralelos.
 */
class VerticalSeeder extends Seeder
{
    /**
     * codigo => nombre.
     *
     * @var array<string, string>
     */
    private const VERTICALES = [
        'barberia' => 'Barbería',
        'estetica' => 'Estética',
        'unas' => 'Uñas',
        'mascotas' => 'Mascotas',
    ];

    public function run(): void
    {
        foreach (self::VERTICALES as $codigo => $nombre) {
            Vertical::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'activo' => true]);
        }
    }
}
