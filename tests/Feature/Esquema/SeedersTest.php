<?php

namespace Tests\Feature\Esquema;

use App\Models\Amenidad;
use App\Models\CatalogoServicio;
use App\Models\ServicioCategoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El catálogo lo define la plataforma, no los locales (§4.5).
 *
 * Los seeders también sirven para publicar cambios del catálogo sobre una base
 * que ya tiene datos, así que tienen que poder correr dos veces sin duplicar.
 */
class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_semillas_pueblan_el_catalogo(): void
    {
        $this->seed();

        $this->assertSame(17, ServicioCategoria::count());
        $this->assertSame(25, CatalogoServicio::count());
        $this->assertSame(27, Amenidad::count());
    }

    public function test_correr_las_semillas_dos_veces_no_duplica(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(17, ServicioCategoria::count());
        $this->assertSame(25, CatalogoServicio::count());
        $this->assertSame(27, Amenidad::count());
    }

    public function test_las_cuatro_verticales_tienen_categorias_y_servicios(): void
    {
        $this->seed();

        foreach (['barberia', 'estetica', 'unas', 'mascotas'] as $vertical) {
            $this->assertGreaterThan(
                0,
                ServicioCategoria::whereHas('vertical', fn ($q) => $q->where('codigo', $vertical))->count(),
                "La vertical '{$vertical}' se quedó sin categorías.",
            );
            $this->assertGreaterThan(
                0,
                CatalogoServicio::whereHas('categoria.vertical', fn ($q) => $q->where('codigo', $vertical))->count(),
                "La vertical '{$vertical}' se quedó sin servicios.",
            );
        }
    }

    public function test_los_slugs_del_catalogo_son_unicos(): void
    {
        $this->seed();

        $this->assertSame(
            CatalogoServicio::count(),
            CatalogoServicio::distinct()->count('slug'),
        );
    }

    /**
     * "Acepta mascotas en sala" es una amenidad; "baña perros" es un servicio de
     * la vertical mascotas. Confundirlas lleva clientes con su perro a un local
     * que solo lo deja entrar (§4.4).
     */
    public function test_acepta_mascotas_es_amenidad_y_no_servicio(): void
    {
        $this->seed();

        $this->assertTrue(Amenidad::where('codigo', 'acepta_mascotas_en_sala')->exists());
        $this->assertFalse(CatalogoServicio::where('nombre', 'ilike', '%mascota%')->exists());
    }
}
