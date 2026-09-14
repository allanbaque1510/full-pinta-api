<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El catálogo lo define la plataforma, no los locales (§4.5). Público, sin
 * autenticación — el front lo necesita antes de que exista ninguna cuenta.
 */
class CatalogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_categorias_son_publicas_y_vienen_de_las_semillas(): void
    {
        $this->seed();

        $this->getJson('/api/v1/catalogo/categorias')
            ->assertOk()
            ->assertJsonCount(17);
    }

    public function test_las_categorias_se_filtran_por_vertical(): void
    {
        $this->seed();

        $respuesta = $this->getJson('/api/v1/catalogo/categorias?vertical=barberia')->assertOk();

        $this->assertTrue(collect($respuesta->json())->every(fn ($c) => $c['vertical'] === 'barberia'));
    }

    public function test_los_servicios_del_catalogo_son_publicos(): void
    {
        $this->seed();

        $this->getJson('/api/v1/catalogo/servicios')
            ->assertOk()
            ->assertJsonCount(25);
    }

    public function test_los_servicios_se_filtran_por_vertical_y_categoria(): void
    {
        $this->seed();

        $respuesta = $this->getJson('/api/v1/catalogo/servicios?vertical=barberia&categoria=barba')->assertOk();

        $this->assertTrue(collect($respuesta->json())->every(
            fn ($s) => $s['vertical'] === 'barberia' && $s['categoria_codigo'] === 'barba',
        ));
    }

    public function test_las_amenidades_son_publicas_y_vienen_de_las_semillas(): void
    {
        $this->seed();

        $this->getJson('/api/v1/amenidades')
            ->assertOk()
            ->assertJsonCount(27);
    }

    public function test_las_amenidades_se_filtran_por_categoria(): void
    {
        $this->seed();

        $respuesta = $this->getJson('/api/v1/amenidades?categoria=politica')->assertOk();

        $this->assertTrue(collect($respuesta->json())->contains(fn ($a) => $a['codigo'] === 'acepta_mascotas_en_sala'));
        $this->assertTrue(collect($respuesta->json())->every(fn ($a) => $a['categoria'] === 'politica'));
    }
}
