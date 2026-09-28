<?php

namespace Tests\Feature\Directory;

use App\Models\Imagen;
use App\Models\TipoImagen;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * El portafolio del local (§4.4): imágenes de fachada, interior y muestras de
 * trabajo, en la galería polimórfica compartida con el profesional.
 */
class LocalImagenTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // `CrearImagenLocalRequest`/`ActualizarImagenRequest` validan `tipo`
        // contra `tipo_imagen` real.
        foreach (['fachada', 'interior', 'muestra'] as $codigo) {
            TipoImagen::factory()->create(['codigo' => $codigo, 'nombre' => $codigo]);
        }
    }

    public function test_el_propietario_puede_agregar_una_imagen(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/imagenes", [
                'url' => 'https://ejemplo.com/foto.jpg', 'tipo' => 'fachada', 'orden' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('tipo', 'fachada')
            ->assertJsonPath('objeto_type', 'local')
            ->assertJsonPath('orden', 1);
    }

    public function test_sin_orden_nace_en_cero_no_en_null(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        // DEFAULT en Postgres, no en PHP: si el servicio no lo fija
        // explícito, el modelo recién creado devuelve `null` en vez de 0.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/imagenes", ['url' => 'https://x.com/a.jpg', 'tipo' => 'interior'])
            ->assertCreated()
            ->assertJsonPath('orden', 0);
    }

    public function test_rechaza_un_tipo_de_imagen_invalido(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/imagenes", [
                'url' => 'https://ejemplo.com/foto.jpg', 'tipo' => 'lo-que-sea',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo');
    }

    public function test_listar_las_imagenes_las_devuelve_ordenadas(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        Imagen::factory()->paraLocal($local)->create(['orden' => 2]);
        Imagen::factory()->paraLocal($local)->create(['orden' => 1]);

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/locales/{$local->id}/imagenes")
            ->assertOk();

        $this->assertSame([1, 2], collect($respuesta->json())->pluck('orden')->all());
    }

    public function test_actualizar_solo_el_orden_no_exige_mandar_la_url(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $imagen = Imagen::factory()->paraLocal($local)->create(['orden' => 0]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/imagenes/{$imagen->id}", ['orden' => 5])
            ->assertOk()
            ->assertJsonPath('orden', 5);
    }

    public function test_eliminar_una_imagen_la_borra_de_verdad(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $imagen = Imagen::factory()->paraLocal($local)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/imagenes/{$imagen->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('imagen', ['id' => $imagen->id]);
    }

    public function test_un_recepcionista_no_puede_agregar_imagenes(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/imagenes", ['url' => 'https://x.com/a.jpg', 'tipo' => 'interior'])
            ->assertForbidden();
    }

    public function test_un_extrano_no_puede_ver_las_imagenes(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/locales/{$local->id}/imagenes")
            ->assertForbidden();
    }
}
