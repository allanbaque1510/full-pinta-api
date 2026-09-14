<?php

namespace Tests\Feature\Directory;

use App\Models\Local;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

class LocalTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_crear_un_local_nace_en_borrador(): void
    {
        [, $token, $negocio] = $this->propietarioConNegocio();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/negocios/{$negocio->id}/locales", [
                'nombre' => 'Sucursal Alborada',
                'direccion' => 'Av. Principal 123',
                'lat' => -2.1300,
                'lng' => -79.8862,
            ])
            ->assertCreated()
            ->assertJsonPath('estado', 'borrador')
            ->assertJsonPath('lat', -2.13)
            ->assertJsonPath('lng', -79.8862)
            // Todos estos tienen DEFAULT en Postgres, no en PHP: sin fijarlos
            // en el servicio, el modelo recién creado devuelve `null` en vez
            // del default real (nunca vuelve a leer la fila de la base).
            ->assertJsonPath('verificado', false)
            ->assertJsonPath('lead_time_min', 60)
            ->assertJsonPath('horizonte_dias', 30)
            ->assertJsonPath('politica_cancelacion_horas', 2);
    }

    public function test_un_recepcionista_no_puede_crear_locales_pero_si_verlos(): void
    {
        [, , $negocio] = $this->propietarioConNegocio();
        [, $token] = $this->recepcionEnNegocio($negocio);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/negocios/{$negocio->id}/locales", [
                'nombre' => 'X', 'direccion' => 'Y', 'lat' => 0, 'lng' => 0,
            ])
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/negocios/{$negocio->id}/locales")
            ->assertOk();
    }

    public function test_activar_un_local_en_borrador_lo_pone_activo(): void
    {
        [, $token, $negocio] = $this->propietarioConNegocio();
        $localId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/negocios/{$negocio->id}/locales", [
                'nombre' => 'X', 'direccion' => 'Y', 'lat' => 0, 'lng' => 0,
            ])->json('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$localId}/activar")
            ->assertOk()
            ->assertJsonPath('estado', 'activo');
    }

    public function test_pausar_un_local_que_no_esta_activo_falla(): void
    {
        [, $token, $negocio] = $this->propietarioConNegocio();
        $localId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/negocios/{$negocio->id}/locales", [
                'nombre' => 'X', 'direccion' => 'Y', 'lat' => 0, 'lng' => 0,
            ])->json('id'); // nace en 'borrador'

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$localId}/pausar")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');
    }

    public function test_activar_y_luego_pausar_y_reactivar_funciona(): void
    {
        [, $token, $negocio] = $this->propietarioConNegocio();
        $localId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/negocios/{$negocio->id}/locales", [
                'nombre' => 'X', 'direccion' => 'Y', 'lat' => 0, 'lng' => 0,
            ])->json('id');

        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/locales/{$localId}/activar")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$localId}/pausar")
            ->assertOk()
            ->assertJsonPath('estado', 'pausado');
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$localId}/activar")
            ->assertOk()
            ->assertJsonPath('estado', 'activo');
    }

    public function test_un_extrano_sin_membresia_no_ve_el_local(): void
    {
        // Ojo: nunca autenticar dos usuarios distintos por Bearer dentro del
        // MISMO test. El guard de Sanctum cachea el usuario resuelto en el
        // request de prueba, así que una segunda petición con otro token
        // vuelve a resolver el primer usuario. Por eso el local del "otro"
        // negocio se crea directo por factory, no a través del propietario.
        $local = Local::factory()->create();

        $otro = Usuario::factory()->create();
        $otroToken = $otro->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$otroToken}")
            ->getJson("/api/v1/locales/{$local->id}")
            ->assertForbidden();
    }

    public function test_actualizar_solo_lat_no_pierde_el_lng_existente(): void
    {
        [, $token, $negocio] = $this->propietarioConNegocio();
        $localId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/negocios/{$negocio->id}/locales", [
                // Coordenadas sin ceros de más: -79.0 se codifica como -79 en
                // JSON y rompe una comparación estricta contra -79.0 en PHP.
                'nombre' => 'X', 'direccion' => 'Y', 'lat' => -2.1234, 'lng' => -79.4321,
            ])->json('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/locales/{$localId}", ['lat' => -2.5678])
            ->assertOk()
            ->assertJsonPath('lat', -2.5678)
            ->assertJsonPath('lng', -79.4321);
    }
}
