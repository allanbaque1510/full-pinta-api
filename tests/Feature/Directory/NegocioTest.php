<?php

namespace Tests\Feature\Directory;

use App\Models\Negocio;
use App\Models\Plan;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NegocioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // `NegocioService::crear` asume que el plan "free" ya está sembrado en
        // producción (§4.4) — en test hay que sembrarlo a mano.
        Plan::factory()->create(['codigo' => 'free', 'nombre' => 'Free']);
    }

    private function autenticado(): array
    {
        $usuario = Usuario::factory()->create();

        return [$usuario, $usuario->createToken('t')->plainTextToken];
    }

    public function test_crear_un_negocio_lo_convierte_en_propietario(): void
    {
        [$usuario, $token] = $this->autenticado();

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/negocios', ['nombre_marca' => 'Barbería Kevin'])
            ->assertCreated()
            ->assertJsonPath('nombre_marca', 'Barbería Kevin')
            ->assertJsonPath('propietario_id', $usuario->id);

        $this->assertDatabaseHas('negocio_miembro', [
            'usuario_id' => $usuario->id,
            'negocio_id' => $respuesta->json('id'),
            'rol' => 'propietario',
            'local_id' => null,
        ]);
    }

    public function test_un_negocio_nace_en_plan_free(): void
    {
        [, $token] = $this->autenticado();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/negocios', ['nombre_marca' => 'X'])
            ->assertJsonPath('plan', 'free');
    }

    public function test_un_extrano_no_puede_ver_ni_editar_el_negocio_de_otro(): void
    {
        $negocio = Negocio::factory()->create();
        [, $token] = $this->autenticado();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/negocios/{$negocio->id}")
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/negocios/{$negocio->id}", ['nombre_marca' => 'Robado'])
            ->assertForbidden();
    }

    public function test_el_propietario_puede_editar_su_negocio(): void
    {
        $usuario = Usuario::factory()->create();
        $this->postJson('/api/v1/negocios', ['nombre_marca' => 'Original'])
            ->assertUnauthorized(); // sin token, control de que la ruta exige auth

        $token = $usuario->createToken('t')->plainTextToken;
        $id = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/negocios', ['nombre_marca' => 'Original'])
            ->json('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/negocios/{$id}", ['nombre_marca' => 'Renombrado'])
            ->assertOk()
            ->assertJsonPath('nombre_marca', 'Renombrado');
    }

    public function test_rechaza_un_ruc_con_formato_invalido(): void
    {
        [, $token] = $this->autenticado();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/negocios', ['nombre_marca' => 'X', 'ruc' => '123'])
            ->assertUnprocessable();
    }
}
