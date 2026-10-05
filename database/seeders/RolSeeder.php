<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * Los cinco roles de acceso de la matriz del §3.2. `cliente` y `profesional`
 * no tienen membresía en `negocio_miembro` — se resuelven según si el
 * usuario tiene una asignación vigente como profesional en el local
 * consultado (ver `ContextoAcceso::rolDeAccesoEnLocal()`).
 */
class RolSeeder extends Seeder
{
    /**
     * codigo => nombre, en orden de menor a mayor alcance administrativo.
     *
     * @var array<string, string>
     */
    private const ROLES = [
        'cliente' => 'Cliente',
        'profesional' => 'Profesional',
        'recepcion' => 'Recepción',
        'propietario' => 'Propietario',
        'admin' => 'Administrador',
    ];

    public function run(): void
    {
        $orden = 0;

        foreach (self::ROLES as $codigo => $nombre) {
            Rol::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'orden' => ++$orden, 'activo' => true],
            );
        }
    }
}
