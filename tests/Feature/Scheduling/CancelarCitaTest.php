<?php

namespace Tests\Feature\Scheduling;

use App\Models\Cita;
use App\Models\ClientePerfil;
use App\Models\Local;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/** `cancelar` exige `Idempotency-Key` (§12.4) — cada test manda la suya. */
class CancelarCitaTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    private function headers(string $token): array
    {
        return ['Authorization' => "Bearer {$token}", 'Idempotency-Key' => (string) Str::uuid()];
    }

    public function test_el_cliente_cancela_su_propia_cita_con_anticipacion(): void
    {
        $cliente = Usuario::factory()->create();
        ClientePerfil::factory()->create(['usuario_id' => $cliente->id]);
        $local = Local::factory()->create(['politica_cancelacion_horas' => 2]);
        $cita = Cita::factory()->create([
            'cliente_id' => $cliente->id, 'local_id' => $local->id,
            'inicio' => now()->addDays(3), 'fin' => now()->addDays(3)->addHour(),
        ]);

        $this->withHeaders($this->headers($cliente->createToken('t')->plainTextToken))
            ->postJson("/api/v1/citas/{$cita->id}/cancelar")
            ->assertOk()
            ->assertJsonPath('estado', 'cancelada_cliente');

        $this->assertDatabaseHas('cliente_perfil', ['usuario_id' => $cliente->id, 'cancelaciones_tardias' => 0]);
    }

    /** "Cliente cancela 10 min antes" (§5.6): dentro de la ventana de política, cuenta como tardía. */
    public function test_cancelar_dentro_de_la_ventana_de_politica_cuenta_como_tardia(): void
    {
        $cliente = Usuario::factory()->create();
        ClientePerfil::factory()->create(['usuario_id' => $cliente->id]);
        $local = Local::factory()->create(['politica_cancelacion_horas' => 2]);
        $cita = Cita::factory()->create([
            'cliente_id' => $cliente->id, 'local_id' => $local->id,
            'inicio' => now()->addHour(), 'fin' => now()->addHours(2),
        ]);

        $this->withHeaders($this->headers($cliente->createToken('t')->plainTextToken))
            ->postJson("/api/v1/citas/{$cita->id}/cancelar")
            ->assertOk()
            ->assertJsonPath('estado', 'cancelada_cliente');

        $this->assertDatabaseHas('cliente_perfil', ['usuario_id' => $cliente->id, 'cancelaciones_tardias' => 1]);
        $this->assertDatabaseHas('cita_bitacora', ['cita_id' => $cita->id, 'estado_nuevo' => 'cancelada_cliente']);
    }

    /**
     * Regresión (encontrado probando el flujo completo en vivo, 2026-09-30):
     * nada crea `cliente_perfil` al registrar un usuario — a propósito, no
     * todo usuario llega a agendar. Antes del fix, `ClientePerfil::where(...)
     * ->increment(...)` actualizaba 0 filas en silencio contra un cliente
     * real (sin fila pre-creada): la métrica nunca se movía fuera de los
     * tests (que sí la pre-creaban).
     */
    public function test_cancelacion_tardia_funciona_aunque_el_cliente_nunca_tuvo_fila_en_cliente_perfil(): void
    {
        $cliente = Usuario::factory()->create();
        $local = Local::factory()->create(['politica_cancelacion_horas' => 2]);
        $cita = Cita::factory()->create([
            'cliente_id' => $cliente->id, 'local_id' => $local->id,
            'inicio' => now()->addHour(), 'fin' => now()->addHours(2),
        ]);

        $this->assertDatabaseCount('cliente_perfil', 0);

        $this->withHeaders($this->headers($cliente->createToken('t')->plainTextToken))
            ->postJson("/api/v1/citas/{$cita->id}/cancelar")
            ->assertOk()
            ->assertJsonPath('estado', 'cancelada_cliente');

        $this->assertDatabaseHas('cliente_perfil', ['usuario_id' => $cliente->id, 'cancelaciones_tardias' => 1]);
    }

    public function test_el_staff_cancela_como_cancelada_local(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cita = Cita::factory()->create(['local_id' => $local->id]);

        $this->withHeaders($this->headers($token))
            ->postJson("/api/v1/citas/{$cita->id}/cancelar")
            ->assertOk()
            ->assertJsonPath('estado', 'cancelada_local');
    }

    public function test_no_se_puede_cancelar_una_cita_terminal(): void
    {
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->completada()->create(['cliente_id' => $cliente->id]);

        $this->withHeaders($this->headers($cliente->createToken('t')->plainTextToken))
            ->postJson("/api/v1/citas/{$cita->id}/cancelar")
            ->assertUnprocessable();
    }

    public function test_un_extrano_no_puede_cancelar_la_cita_de_otro(): void
    {
        $cita = Cita::factory()->create();
        $otro = Usuario::factory()->create();

        $this->withHeaders($this->headers($otro->createToken('t')->plainTextToken))
            ->postJson("/api/v1/citas/{$cita->id}/cancelar")
            ->assertForbidden();
    }
}
