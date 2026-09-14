<?php

namespace Tests\Feature\Billing;

use App\Models\Negocio;
use App\Models\NegocioMiembro;
use App\Models\Plan;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/** Suscripción al plan Pro (§9.6, §10.1): administrativo, sin pasarela de pago. */
class SuscripcionTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_activar_calcula_el_precio_segun_la_cantidad_de_profesionales(): void
    {
        Plan::factory()->create(['codigo' => 'pro']);
        [, $token, $negocio] = $this->propietarioConNegocio();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/negocios/{$negocio->id}/suscripcion", [
                'plan' => 'pro', 'profesionales' => 4, 'ciclo' => 'mensual',
            ])
            ->assertCreated()
            ->assertJsonPath('plan', 'pro')
            ->assertJsonPath('precio_mensual', '23.00'); // 8 + 5*3

        $this->assertTrue($negocio->fresh()->esPro());
    }

    public function test_un_admin_no_puede_gestionar_la_suscripcion_solo_el_propietario_legal(): void
    {
        Plan::factory()->create(['codigo' => 'pro']);
        [, , $negocio] = $this->propietarioConNegocio();

        $admin = Usuario::factory()->create();
        NegocioMiembro::factory()->create(['usuario_id' => $admin->id, 'negocio_id' => $negocio->id, 'local_id' => null, 'rol' => 'admin']);

        $this->withHeader('Authorization', "Bearer {$admin->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/negocios/{$negocio->id}/suscripcion", [
                'plan' => 'pro', 'profesionales' => 1, 'ciclo' => 'mensual',
            ])
            ->assertForbidden();
    }

    public function test_cancelar_vuelve_el_negocio_a_free(): void
    {
        Plan::factory()->create(['codigo' => 'pro']);
        Plan::factory()->create(['codigo' => 'free']);
        [, $token, $negocio] = $this->propietarioConNegocio();

        $suscripcionId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/negocios/{$negocio->id}/suscripcion", [
                'plan' => 'pro', 'profesionales' => 1, 'ciclo' => 'mensual',
            ])->json('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/suscripciones/{$suscripcionId}/cancelar")
            ->assertOk()
            ->assertJsonPath('estado', 'cancelada');

        $this->assertFalse($negocio->fresh()->esPro());
    }

    public function test_un_extrano_no_puede_ver_la_suscripcion_de_otro_negocio(): void
    {
        $negocio = Negocio::factory()->create();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/negocios/{$negocio->id}/suscripcion")
            ->assertForbidden();
    }
}
