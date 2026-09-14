<?php

namespace Tests\Feature\Directory;

use App\Models\Amenidad;
use App\Models\Favorito;
use App\Models\Local;
use App\Models\ServicioLocal;
use App\Models\Usuario;
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
}
