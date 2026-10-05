<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Agrupa los tres seeders de `rol`/`permiso`/`rol_permiso` (§3.2) para
 * poder sembrarlos con un solo `--seeder` en `migrate:fresh` — lo usa
 * `tests\TestCase` para que esté disponible UNA sola vez por corrida de
 * tests (antes de la transacción de cualquier test), no en cada uno.
 */
class RolesYPermisosBaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolSeeder::class,
            PermisoSeeder::class,
            RolPermisoSeeder::class,
        ]);
    }
}
