<?php

namespace Tests\Feature\Scheduling;

use App\Models\Cita;
use App\Models\ClientePerfil;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

class MarcarNoShowTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_el_staff_marca_no_show(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cliente = Usuario::factory()->create();
        ClientePerfil::factory()->create(['usuario_id' => $cliente->id]);
        $cita = Cita::factory()->create(['local_id' => $local->id, 'cliente_id' => $cliente->id, 'estado' => 'confirmada']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/citas/{$cita->id}/no-show")
            ->assertOk()
            ->assertJsonPath('estado', 'no_show');

        $this->assertDatabaseHas('cliente_perfil', ['usuario_id' => $cliente->id, 'no_shows' => 1]);
    }

    /**
     * Regresión (encontrado probando el flujo completo en vivo, 2026-09-30):
     * nada crea `cliente_perfil` al registrar un usuario — a propósito, no
     * todo usuario llega a agendar. Antes del fix, `ClientePerfil::where(...)
     * ->first()` devolvía `null` para cualquier cliente real y el bloque
     * `if ($clientePerfil !== null)` lo saltaba en silencio: `no_shows` nunca
     * se movía para nadie fuera de los tests (que sí pre-creaban la fila).
     */
    public function test_funciona_aunque_el_cliente_nunca_tuvo_fila_en_cliente_perfil(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->create(['local_id' => $local->id, 'cliente_id' => $cliente->id, 'estado' => 'confirmada']);

        $this->assertDatabaseCount('cliente_perfil', 0);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/citas/{$cita->id}/no-show")
            ->assertOk()
            ->assertJsonPath('estado', 'no_show');

        $this->assertDatabaseHas('cliente_perfil', ['usuario_id' => $cliente->id, 'no_shows' => 1]);
    }

    public function test_el_cliente_no_puede_marcarse_no_show_a_si_mismo(): void
    {
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->create(['cliente_id' => $cliente->id, 'estado' => 'confirmada']);

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$cita->id}/no-show")
            ->assertForbidden();
    }

    /** "Cliente con 3 no-shows: requiere_confirmacion = true" (§5.6). */
    public function test_al_tercer_no_show_activa_requiere_confirmacion(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cliente = Usuario::factory()->create();
        ClientePerfil::factory()->create(['usuario_id' => $cliente->id, 'no_shows' => 2]);
        $cita = Cita::factory()->create(['local_id' => $local->id, 'cliente_id' => $cliente->id, 'estado' => 'confirmada']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/citas/{$cita->id}/no-show")
            ->assertOk();

        $this->assertDatabaseHas('cliente_perfil', [
            'usuario_id' => $cliente->id, 'no_shows' => 3, 'requiere_confirmacion' => true,
        ]);
    }

    public function test_no_se_puede_marcar_no_show_una_cita_reservada(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cita = Cita::factory()->reservada()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/citas/{$cita->id}/no-show")
            ->assertUnprocessable();
    }
}
