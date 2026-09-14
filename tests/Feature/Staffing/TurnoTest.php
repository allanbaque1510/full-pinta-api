<?php

namespace Tests\Feature\Staffing;

use App\Models\Asignacion;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\Turno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * El horario recurrente de un profesional (§4.6, §4.11) — el constraint
 * `turno_sin_traslape` es la única fuente de verdad contra solapamientos.
 */
class TurnoTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_el_propietario_puede_crear_un_turno_recurrente(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/asignaciones/{$asignacion->id}/turnos", [
                'dia_semana' => 1, 'entra' => '09:00', 'sale' => '18:00',
                'vigente_desde' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('entra', '09:00')
            ->assertJsonPath('sale', '18:00')
            ->assertJsonPath('local_id', $local->id);
    }

    public function test_rechaza_un_turno_que_se_traslapa_con_otro_del_mismo_profesional(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id]);
        Turno::factory()->para($asignacion)->horario('09:00', '18:00', 1)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/asignaciones/{$asignacion->id}/turnos", [
                'dia_semana' => 1, 'entra' => '10:00', 'sale' => '20:00',
                'vigente_desde' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entra');
    }

    /** Un profesional es un solo cuerpo: no puede tener turnos encimados en dos locales distintos (§4.11). */
    public function test_un_profesional_no_puede_tener_turnos_traslapados_en_dos_locales(): void
    {
        [, $token, $negocio, $localA] = $this->propietarioConLocal();
        $localB = Local::factory()->create(['negocio_id' => $negocio->id]);
        $kevin = Profesional::factory()->create();
        $asignacionA = Asignacion::factory()->create(['local_id' => $localA->id, 'profesional_id' => $kevin->id]);
        $asignacionB = Asignacion::factory()->create(['local_id' => $localB->id, 'profesional_id' => $kevin->id]);
        Turno::factory()->para($asignacionA)->horario('09:00', '18:00', 2)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/asignaciones/{$asignacionB->id}/turnos", [
                'dia_semana' => 2, 'entra' => '09:00', 'sale' => '18:00',
                'vigente_desde' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entra');
    }

    public function test_rechaza_hora_de_salida_menor_o_igual_a_la_de_entrada(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/asignaciones/{$asignacion->id}/turnos", [
                'dia_semana' => 1, 'entra' => '18:00', 'sale' => '09:00',
                'vigente_desde' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sale');
    }

    public function test_actualizar_un_turno(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id]);
        $turno = Turno::factory()->para($asignacion)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/turnos/{$turno->id}", ['entra' => '10:00', 'sale' => '19:00'])
            ->assertOk()
            ->assertJsonPath('entra', '10:00')
            ->assertJsonPath('sale', '19:00');
    }

    public function test_eliminar_un_turno(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id]);
        $turno = Turno::factory()->para($asignacion)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/turnos/{$turno->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('turno', ['id' => $turno->id]);
    }

    public function test_un_recepcionista_no_puede_crear_turnos(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/asignaciones/{$asignacion->id}/turnos", [
                'dia_semana' => 1, 'entra' => '09:00', 'sale' => '18:00',
                'vigente_desde' => now()->toDateString(),
            ])
            ->assertForbidden();
    }
}
