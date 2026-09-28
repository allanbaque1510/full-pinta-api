<?php

namespace Tests\Feature\Directory;

use App\Models\Amenidad;
use App\Models\CatalogoServicio;
use App\Models\Cita;
use App\Models\Favorito;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\ServicioLocal;
use App\Models\Usuario;
use App\Modules\Catalog\Application\ProductoService;
use App\Modules\Catalog\Application\ServicioLocalService;
use App\Modules\Reviews\Application\ResenaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * Perfil público del local (§7.4): visible sin cuenta, solo si está `activo`,
 * cacheado 1h e invalidado al editar.
 */
class PerfilPublicoLocalTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_muestra_el_perfil_publico_de_un_local_activo(): void
    {
        $local = Local::factory()->create(['nombre' => 'Barbería Central']);
        $amenidad = Amenidad::factory()->create();
        $local->amenidades()->attach($amenidad->id);
        ServicioLocal::factory()->create(['local_id' => $local->id, 'activo' => true]);

        $this->getJson("/api/v1/locales/{$local->id}/perfil-publico")
            ->assertOk()
            ->assertJsonPath('id', $local->id)
            ->assertJsonPath('nombre', 'Barbería Central')
            ->assertJsonCount(1, 'amenidades')
            ->assertJsonCount(1, 'servicios');
    }

    public function test_un_local_no_activo_devuelve_404(): void
    {
        $local = Local::factory()->borrador()->create();

        $this->getJson("/api/v1/locales/{$local->id}/perfil-publico")->assertNotFound();
    }

    public function test_el_perfil_publico_se_cachea_y_se_invalida_al_editar(): void
    {
        [, $token, , $local] = $this->propietarioConLocal(); // nace 'activo' (factory)

        $this->getJson("/api/v1/locales/{$local->id}/perfil-publico")
            ->assertJsonPath('nombre', $local->nombre);

        $this->assertNotNull(Cache::get("local:perfil-publico:{$local->id}"));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/locales/{$local->id}", ['nombre' => 'Nuevo Nombre'])
            ->assertOk();

        $this->assertNull(Cache::get("local:perfil-publico:{$local->id}"));

        $this->getJson("/api/v1/locales/{$local->id}/perfil-publico")
            ->assertJsonPath('nombre', 'Nuevo Nombre');
    }

    public function test_es_favorito_es_false_sin_autenticacion(): void
    {
        $local = Local::factory()->create();

        $this->getJson("/api/v1/locales/{$local->id}/perfil-publico")
            ->assertJsonPath('es_favorito', false);
    }

    public function test_es_favorito_es_true_para_un_cliente_que_lo_marco(): void
    {
        $local = Local::factory()->create();
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('t')->plainTextToken;
        Favorito::factory()->create(['usuario_id' => $usuario->id, 'local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/locales/{$local->id}/perfil-publico")
            ->assertJsonPath('es_favorito', true);
    }

    /**
     * El perfil público se cachea 1h y se comparte entre TODOS los que lo
     * consulten (misma clave, sin usuario en ella) — `es_favorito` tiene que
     * calcularse siempre por fuera de ese bloque, o el primer usuario que
     * "calienta" el caché le fijaría su propio valor a cualquiera que lo lea
     * después.
     *
     * No se simula esto con dos peticiones (una autenticada y otra anónima)
     * en el mismo test: el guard de Sanctum resuelve y cachea el usuario en
     * la primera petición y lo sigue devolviendo en las siguientes de ese
     * mismo test, aunque la segunda no mande `Authorization` — la misma
     * trampa que dos `Bearer` distintos en un test (CLAUDE.md). Se inspecciona
     * directo el valor guardado en caché en su lugar: es la única forma de
     * probar el invariante sin depender del estado del guard entre requests.
     */
    public function test_es_favorito_no_entra_al_cache_compartido_del_local(): void
    {
        $local = Local::factory()->create();
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('t')->plainTextToken;
        Favorito::factory()->create(['usuario_id' => $usuario->id, 'local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/locales/{$local->id}/perfil-publico")
            ->assertJsonPath('es_favorito', true);

        $cacheado = Cache::get("local:perfil-publico:{$local->id}");

        $this->assertIsArray($cacheado);
        $this->assertArrayNotHasKey('es_favorito', $cacheado);
    }

    /**
     * Revisión de base de datos 2026-09-28: antes, `ServicioLocalService`
     * (Catalog) nunca invalidaba este caché — el perfil público quedaba
     * mostrando la lista de servicios vieja hasta que expirara el TTL de 1h.
     */
    public function test_el_perfil_publico_se_invalida_al_agregar_un_servicio_desde_catalog(): void
    {
        $local = Local::factory()->create();
        $catalogoServicio = CatalogoServicio::factory()->create();

        $this->getJson("/api/v1/locales/{$local->id}/perfil-publico")->assertJsonCount(0, 'servicios');
        $this->assertNotNull(Cache::get("local:perfil-publico:{$local->id}"));

        app(ServicioLocalService::class)->crear($local, [
            'catalogo_servicio_id' => $catalogoServicio->id,
            'precio' => 10,
            'duracion_min' => 30,
        ]);

        $this->assertNull(Cache::get("local:perfil-publico:{$local->id}"));
        $this->getJson("/api/v1/locales/{$local->id}/perfil-publico")->assertJsonCount(1, 'servicios');
    }

    /**
     * Revisión de base de datos 2026-09-28: los productos activos también
     * aparecen en el perfil público, y su alta/edición/baja debe invalidar
     * el mismo caché — mismo patrón ya usado para servicios/amenidades/imágenes.
     */
    public function test_el_perfil_publico_se_invalida_al_agregar_un_producto(): void
    {
        $local = Local::factory()->create();

        $this->getJson("/api/v1/locales/{$local->id}/perfil-publico")->assertJsonCount(0, 'productos');
        $this->assertNotNull(Cache::get("local:perfil-publico:{$local->id}"));

        app(ProductoService::class)->crear($local, ['nombre' => 'Pomada', 'precio' => 10]);

        $this->assertNull(Cache::get("local:perfil-publico:{$local->id}"));
        $this->getJson("/api/v1/locales/{$local->id}/perfil-publico")->assertJsonCount(1, 'productos');
    }

    /**
     * Mismo hallazgo, del lado de Reviews: `ResenaService::crear()` tampoco
     * invalidaba este caché.
     */
    public function test_el_perfil_publico_se_invalida_al_crear_una_resena_desde_reviews(): void
    {
        $local = Local::factory()->create();
        $profesional = Profesional::factory()->create();
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->completada()->create([
            'local_id' => $local->id, 'profesional_id' => $profesional->id, 'cliente_id' => $cliente->id,
        ]);

        $this->getJson("/api/v1/locales/{$local->id}/perfil-publico")->assertJsonPath('resenas.total', 0);
        $this->assertNotNull(Cache::get("local:perfil-publico:{$local->id}"));

        app(ResenaService::class)->crear($cita, ['puntaje_local' => 5]);

        $this->assertNull(Cache::get("local:perfil-publico:{$local->id}"));
        $this->getJson("/api/v1/locales/{$local->id}/perfil-publico")->assertJsonPath('resenas.total', 1);
    }

    /**
     * Revisión de base de datos 2026-09-28: un local con `verificado: true`
     * no debe mostrarse verificado al público si el negocio dueño no tiene
     * el RUC verificado — antes solo se miraba `local.verificado`.
     */
    public function test_verificado_exige_tambien_el_ruc_verificado_del_negocio(): void
    {
        $local = Local::factory()->create(['verificado' => true]);

        $this->getJson("/api/v1/locales/{$local->id}/perfil-publico")
            ->assertJsonPath('verificado', false);

        $local->negocio->update(['ruc_verificado' => true]);
        Cache::forget("local:perfil-publico:{$local->id}");

        $this->getJson("/api/v1/locales/{$local->id}/perfil-publico")
            ->assertJsonPath('verificado', true);
    }
}
