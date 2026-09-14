<?php

namespace Tests\Feature\Identity;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Operar la cita no es lo mismo que recibir promociones (§4.3, §13.1): cada
 * finalidad se otorga y se revoca por separado.
 */
class ConsentimientoTest extends TestCase
{
    use RefreshDatabase;

    private function autenticado(): array
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('t')->plainTextToken;

        return [$usuario, $token];
    }

    public function test_otorgar_marketing_crea_un_consentimiento_vigente(): void
    {
        [, $token] = $this->autenticado();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/consentimientos', ['finalidad' => 'marketing', 'otorgado' => true])
            ->assertOk()
            ->assertJsonPath('finalidad', 'marketing')
            ->assertJsonPath('otorgado', true)
            ->assertJsonPath('vigente', true);

        $this->assertDatabaseHas('consentimiento', ['finalidad' => 'marketing', 'otorgado' => true]);
    }

    public function test_revocar_marketing_actualiza_la_fila_en_vez_de_crear_otra(): void
    {
        [$usuario, $token] = $this->autenticado();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/consentimientos', ['finalidad' => 'marketing', 'otorgado' => true]);

        $this->assertSame(1, $usuario->consentimientos()->count());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/consentimientos', ['finalidad' => 'marketing', 'otorgado' => false])
            ->assertOk()
            ->assertJsonPath('vigente', false);

        // Sigue siendo UNA fila, ahora con revocado_at puesto — no dos.
        $this->assertSame(1, $usuario->consentimientos()->count());
        $this->assertNotNull($usuario->consentimientos()->first()->revocado_at);
    }

    public function test_revocar_algo_nunca_otorgado_no_falla_y_no_crea_nada(): void
    {
        [, $token] = $this->autenticado();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/consentimientos', ['finalidad' => 'marketing', 'otorgado' => false])
            ->assertOk()
            ->assertJsonPath('vigente', false);

        $this->assertDatabaseCount('consentimiento', 0);
    }

    public function test_index_devuelve_el_estado_mas_reciente_por_finalidad(): void
    {
        [$usuario, $token] = $this->autenticado();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/consentimientos', ['finalidad' => 'marketing', 'otorgado' => true]);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/consentimientos', ['finalidad' => 'operacion_servicio', 'otorgado' => true]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/consentimientos')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_rechaza_una_finalidad_que_no_existe(): void
    {
        [, $token] = $this->autenticado();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/consentimientos', ['finalidad' => 'lo_que_sea', 'otorgado' => true])
            ->assertUnprocessable();
    }
}
