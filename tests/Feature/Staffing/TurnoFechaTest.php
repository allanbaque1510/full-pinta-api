<?php

namespace Tests\Feature\Staffing;

use App\Models\Asignacion;
use App\Models\Profesional;
use App\Models\TurnoFecha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * Overrides por fecha concreta sobre el turno recurrente (§4.6) — "este
 * sábado no voy a Urdesa, voy a Alborada".
 */
class TurnoFechaTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_el_propietario_puede_crear_un_override_extra(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/turno-fechas", [
                'local_id' => $local->id, 'fecha' => now()->addWeek()->toDateString(),
                'tipo' => 'extra', 'entra' => '10:00', 'sale' => '16:00',
            ])
            ->assertCreated()
            ->assertJsonPath('tipo', 'extra')
            ->assertJsonPath('entra', '10:00');
    }

    public function test_una_cancelacion_no_lleva_horas(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/turno-fechas", [
                'local_id' => $local->id, 'fecha' => now()->addWeek()->toDateString(), 'tipo' => 'cancela',
            ])
            ->assertCreated()
            ->assertJsonPath('tipo', 'cancela')
            ->assertJsonPath('entra', null)
            ->assertJsonPath('sale', null);
    }

    public function test_un_override_que_no_cancela_necesita_horas(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/turno-fechas", [
                'local_id' => $local->id, 'fecha' => now()->addWeek()->toDateString(), 'tipo' => 'extra',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entra');
    }

    public function test_eliminar_un_override(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        $turnoFecha = TurnoFecha::factory()->create(['profesional_id' => $profesional->id, 'local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/profesionales/{$profesional->id}/turno-fechas/{$turnoFecha->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('turno_fecha', ['id' => $turnoFecha->id]);
    }

    public function test_un_recepcionista_no_puede_crear_overrides(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/turno-fechas", [
                'local_id' => $local->id, 'fecha' => now()->addWeek()->toDateString(),
                'tipo' => 'extra', 'entra' => '10:00', 'sale' => '16:00',
            ])
            ->assertForbidden();
    }
}
