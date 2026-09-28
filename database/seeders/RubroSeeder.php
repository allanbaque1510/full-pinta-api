<?php

namespace Database\Seeders;

use App\Models\Rubro;
use Illuminate\Database\Seeder;

/**
 * Rubros del catálogo maestro (§4.5): barbería, estética, uñas, mascotas.
 *
 * Son un campo del catálogo, no tipos distintos de local — un local puede
 * ofrecer corte de caballero y uñas sin dos modelos paralelos. Se llamaban
 * `vertical` hasta el 2026-09-28 — renombrado por preferencia de nomenclatura.
 */
class RubroSeeder extends Seeder
{
    /**
     * codigo => nombre.
     *
     * @var array<string, string>
     */
    private const RUBROS = [
        'barberia' => 'Barbería',
        'estetica' => 'Estética',
        'unas' => 'Uñas',
        'mascotas' => 'Mascotas',
    ];

    public function run(): void
    {
        foreach (self::RUBROS as $codigo => $nombre) {
            Rubro::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'activo' => true]);
        }
    }
}
