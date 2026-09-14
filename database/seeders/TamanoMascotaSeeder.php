<?php

namespace Database\Seeders;

use App\Models\TamanoMascota;
use Illuminate\Database\Seeder;

/**
 * Tamaños de mascota (§4.3, §4.5): compartido entre `mascota` (ficha del
 * animal) y `servicio_local_tamano` (precio/duración por tamaño).
 */
class TamanoMascotaSeeder extends Seeder
{
    /**
     * codigo => nombre, en orden de presentación.
     *
     * @var array<string, string>
     */
    private const TAMANOS = [
        'muy_pequeno' => 'Muy pequeño',
        'pequeno' => 'Pequeño',
        'mediano' => 'Mediano',
        'grande' => 'Grande',
        'gigante' => 'Gigante',
    ];

    public function run(): void
    {
        $orden = 0;

        foreach (self::TAMANOS as $codigo => $nombre) {
            TamanoMascota::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'orden' => ++$orden, 'activo' => true],
            );
        }
    }
}
