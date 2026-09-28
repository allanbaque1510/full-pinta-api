<?php

namespace Database\Seeders;

use App\Models\EspecieMascota;
use Illuminate\Database\Seeder;

/**
 * Especies de mascota (§4.3): sembrado inicial modesto, se amplía cuando
 * grooming para otras especies se active.
 */
class EspecieMascotaSeeder extends Seeder
{
    /**
     * codigo => nombre.
     *
     * @var array<string, string>
     */
    private const ESPECIES = [
        'perro' => 'Perro',
        'gato' => 'Gato',
        'otro' => 'Otro',
    ];

    public function run(): void
    {
        foreach (self::ESPECIES as $codigo => $nombre) {
            EspecieMascota::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'activo' => true],
            );
        }
    }
}
