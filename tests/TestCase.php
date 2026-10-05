<?php

namespace Tests;

use Database\Seeders\RolesYPermisosBaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * `rol`/`permiso`/`rol_permiso` (§3.2) son datos de referencia que el
     * propio código de autorización necesita para funcionar
     * (`ContextoAcceso::tienePermiso()`) — no catálogo de negocio opcional
     * como el de servicios o `FinalidadConsentimientoSeeder`, que cada test
     * siembra solo si lo necesita. Sin esto, cualquier test que ejercite una
     * ruta protegida por una Policy (prácticamente todo el suite) fallaría
     * con 403 porque las tablas estarían vacías.
     *
     * `RefreshDatabase` lee esta propiedad y la pasa como `--seeder` a
     * `migrate:fresh` — se siembra UNA sola vez por corrida completa de
     * tests (antes de que exista ninguna transacción de ningún test), no en
     * cada uno: evita repetir ~80 `INSERT` de `permiso` por cada test.
     */
    protected $seeder = RolesYPermisosBaseSeeder::class;
}
