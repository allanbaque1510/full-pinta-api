<?php

namespace Tests\Feature\Scheduling;

use App\Models\Cita;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

class CompletarCitaTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_completar_no_crea_ni_toca_cliente_local(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->create(['local_id' => $local->id, 'cliente_id' => $cliente->id, 'estado' => 'en_curso']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/citas/{$cita->id}/completar", ['propina' => 5])
            ->assertOk()
            ->assertJsonPath('estado', 'completada')
            ->assertJsonPath('propina', '5.00');

        // Los contadores de visitas se calculan en vivo contra `cita`
        // (ver ClienteLocalTest) — completar una cita no crea fila en
        // `cliente_local`, que se queda solo para `nota`/`profesional_preferido_id`.
        $this->assertDatabaseCount('cliente_local', 0);
    }

    public function test_solo_se_puede_completar_una_cita_en_curso(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cita = Cita::factory()->create(['local_id' => $local->id]); // confirmada por defecto

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/citas/{$cita->id}/completar")
            ->assertUnprocessable();
    }

    public function test_un_cliente_no_puede_completar_su_propia_cita(): void
    {
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->create(['cliente_id' => $cliente->id, 'estado' => 'en_curso']);

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$cita->id}/completar")
            ->assertForbidden();
    }
}
