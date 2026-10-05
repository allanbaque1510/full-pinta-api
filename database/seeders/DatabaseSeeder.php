<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos que la plataforma define y ningún local puede cambiar (§4.4, §4.5).
 *
 * Todos los seeders son idempotentes: correrlos de nuevo actualiza en vez de
 * duplicar, así que sirven también para publicar cambios del catálogo en un
 * entorno que ya tiene datos.
 *
 * El orden importa: `servicio_categoria` y `solicitud_catalogo` referencian
 * `rubro` por id, `catalogo_servicio` referencia `servicio_categoria` y
 * `tipo_recurso`, `amenidad` referencia `amenidad_categoria`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TipoImagenSeeder::class,
            RubroSeeder::class,
            ServicioCategoriaSeeder::class,
            TipoRecursoSeeder::class,
            CatalogoServicioSeeder::class,
            AmenidadCategoriaSeeder::class,
            AmenidadSeeder::class,
            MetodoPagoSeeder::class,
            TamanoMascotaSeeder::class,
            EspecieMascotaSeeder::class,
            RazaMascotaSeeder::class,
            PlanSeeder::class,
            NotificacionCategoriaSeeder::class,
            FinalidadConsentimientoSeeder::class,
            RolSeeder::class,
            PermisoSeeder::class,
            RolPermisoSeeder::class,
        ]);
    }
}
