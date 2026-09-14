<?php

namespace Tests\Feature\Directory;

use App\Models\LocalFoto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * El portafolio del local (§4.4): fotos de fachada, interior y trabajos.
 */
class LocalFotoTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_el_propietario_puede_agregar_una_foto(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/fotos", [
                'url' => 'https://ejemplo.com/foto.jpg', 'tipo' => 'fachada', 'orden' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('tipo', 'fachada')
            ->assertJsonPath('orden', 1);
    }

    public function test_sin_orden_nace_en_cero_no_en_null(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        // DEFAULT en Postgres, no en PHP: si el servicio no lo fija
        // explícito, el modelo recién creado devuelve `null` en vez de 0.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/fotos", ['url' => 'https://x.com/a.jpg', 'tipo' => 'interior'])
            ->assertCreated()
            ->assertJsonPath('orden', 0);
    }

    public function test_rechaza_un_tipo_de_foto_invalido(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/fotos", [
                'url' => 'https://ejemplo.com/foto.jpg', 'tipo' => 'lo-que-sea',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo');
    }

    public function test_listar_las_fotos_las_devuelve_ordenadas(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        LocalFoto::factory()->create(['local_id' => $local->id, 'orden' => 2]);
        LocalFoto::factory()->create(['local_id' => $local->id, 'orden' => 1]);

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/locales/{$local->id}/fotos")
            ->assertOk();

        $this->assertSame([1, 2], collect($respuesta->json())->pluck('orden')->all());
    }

    public function test_actualizar_solo_el_orden_no_exige_mandar_la_url(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $foto = LocalFoto::factory()->create(['local_id' => $local->id, 'orden' => 0]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/fotos/{$foto->id}", ['orden' => 5])
            ->assertOk()
            ->assertJsonPath('orden', 5);
    }

    public function test_eliminar_una_foto_la_borra_de_verdad(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $foto = LocalFoto::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/fotos/{$foto->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('local_foto', ['id' => $foto->id]);
    }

    public function test_un_recepcionista_no_puede_agregar_fotos(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/fotos", ['url' => 'https://x.com/a.jpg', 'tipo' => 'interior'])
            ->assertForbidden();
    }

    public function test_un_extrano_no_puede_ver_las_fotos(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/locales/{$local->id}/fotos")
            ->assertForbidden();
    }
}
