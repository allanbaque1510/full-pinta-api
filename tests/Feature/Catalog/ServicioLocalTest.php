<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogoServicio;
use App\Models\ServicioLocal;
use App\Models\TamanoMascota;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * El precio y la duración que un local concreto cobra por un servicio del
 * catálogo maestro (§4.5).
 */
class ServicioLocalTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // `SincronizarTamanosRequest` valida `tamano` contra `tamano_mascota` real.
        foreach (['pequeno', 'grande', 'gigante'] as $codigo) {
            TamanoMascota::factory()->create(['codigo' => $codigo, 'nombre' => $codigo]);
        }
    }

    public function test_el_propietario_puede_dar_de_alta_un_servicio(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $catalogo = CatalogoServicio::factory()->create(['nombre' => 'Corte fade']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/servicios", [
                'catalogo_servicio_id' => $catalogo->id, 'precio' => 8.5, 'duracion_min' => 30,
            ])
            ->assertCreated()
            ->assertJsonPath('precio', '8.50')
            ->assertJsonPath('nombre', 'Corte fade')
            ->assertJsonPath('activo', true)
            // Con DEFAULT en Postgres, no en PHP: si el servicio no los fija
            // explícito, el modelo recién creado devuelve `null` en vez del
            // valor real de la base.
            ->assertJsonPath('precio_desde', false)
            ->assertJsonPath('buffer_min', 0)
            ->assertJsonPath('comisionable', true);
    }

    public function test_no_se_puede_dar_de_alta_el_mismo_servicio_dos_veces(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $catalogo = CatalogoServicio::factory()->create();
        ServicioLocal::factory()->create(['local_id' => $local->id, 'catalogo_servicio_id' => $catalogo->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/servicios", [
                'catalogo_servicio_id' => $catalogo->id, 'precio' => 5, 'duracion_min' => 20,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('catalogo_servicio_id');
    }

    public function test_rechaza_un_servicio_de_catalogo_inactivo(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $catalogo = CatalogoServicio::factory()->create(['activo' => false]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/servicios", [
                'catalogo_servicio_id' => $catalogo->id, 'precio' => 5, 'duracion_min' => 20,
            ])
            ->assertUnprocessable();
    }

    public function test_desactivar_un_servicio_no_lo_borra(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/servicios/{$servicio->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('servicio_local', ['id' => $servicio->id, 'activo' => false]);
    }

    public function test_sincronizar_tamanos_reemplaza_el_conjunto_completo(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/servicios/{$servicio->id}/tamanos", [
                'tamanos' => [
                    ['tamano' => 'pequeno', 'precio' => 15, 'duracion_min' => 30],
                    ['tamano' => 'grande', 'precio' => 25, 'duracion_min' => 50],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/servicios/{$servicio->id}/tamanos", [
                'tamanos' => [['tamano' => 'gigante', 'precio' => 40, 'duracion_min' => 70]],
            ])
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.tamano', 'gigante');

        $this->assertSame(1, $servicio->tamanos()->count());
    }

    public function test_un_recepcionista_no_puede_crear_servicios(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);
        $catalogo = CatalogoServicio::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/servicios", [
                'catalogo_servicio_id' => $catalogo->id, 'precio' => 5, 'duracion_min' => 20,
            ])
            ->assertForbidden();
    }

    public function test_un_extrano_no_puede_listar_los_servicios(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/locales/{$local->id}/servicios")
            ->assertForbidden();
    }
}
