<?php

namespace Database\Seeders;

use App\Models\EspecieMascota;
use App\Models\RazaMascota;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Razas de mascota (§4.3): set inicial modesto por especie, sin exhaustividad
 * — se amplía después con `INSERT`. "Mestizo" es una fila normal, no un caso
 * especial.
 */
class RazaMascotaSeeder extends Seeder
{
    /**
     * codigo_especie => lista de nombres de raza.
     *
     * @var array<string, list<string>>
     */
    private const RAZAS = [
        'perro' => ['Mestizo', 'Golden Retriever', 'Labrador', 'Poodle', 'Schnauzer', 'Yorkshire Terrier', 'Chihuahua', 'Pug'],
        'gato' => ['Mestizo', 'Siamés', 'Persa', 'Angora', 'Maine Coon'],
        'otro' => ['Sin raza definida'],
    ];

    public function run(): void
    {
        foreach (self::RAZAS as $codigoEspecie => $nombres) {
            $especie = EspecieMascota::where('codigo', $codigoEspecie)->firstOrFail();

            foreach ($nombres as $nombre) {
                RazaMascota::updateOrCreate(
                    ['especie_id' => $especie->id, 'codigo' => Str::slug($nombre, '_')],
                    ['nombre' => $nombre, 'activo' => true],
                );
            }
        }
    }
}
