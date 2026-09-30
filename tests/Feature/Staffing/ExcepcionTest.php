<?php

namespace Tests\Feature\Staffing;

use App\Models\Asignacion;
use App\Models\Excepcion;
use App\Models\Profesional;
use App\Models\Recurso;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * `Excepcion` (§4.6): cierres de local, ausencias de profesional y
 * mantenimientos de recurso. Una ausencia de PROFESIONAL bloquea TODOS sus
 * locales, no solo uno.
 */
class ExcepcionTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_el_propietario_puede_crear_una_excepcion_de_local(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/excepciones", [
                'fecha_inicio' => now()->addDay()->toIso8601String(),
                'fecha_fin' => now()->addDays(2)->toIso8601String(),
                'motivo' => 'feriado',
            ])
            ->assertCreated()
            ->assertJsonPath('local_id', $local->id)
            ->assertJsonPath('motivo', 'feriado');
    }

    public function test_el_propietario_puede_crear_una_ausencia_de_profesional(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/excepciones", [
                'fecha_inicio' => now()->addDay()->toIso8601String(),
                'fecha_fin' => now()->addDays(2)->toIso8601String(),
                'motivo' => 'personal',
            ])
            ->assertCreated()
            ->assertJsonPath('profesional_id', $profesional->id)
            ->assertJsonPath('local_id', null);
    }

    public function test_el_propietario_puede_crear_una_excepcion_de_recurso(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $recurso = Recurso::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/recursos/{$recurso->id}/excepciones", [
                'fecha_inicio' => now()->addDay()->toIso8601String(),
                'fecha_fin' => now()->addDays(2)->toIso8601String(),
                'motivo' => 'mantenimiento',
            ])
            ->assertCreated()
            ->assertJsonPath('recurso_id', $recurso->id);
    }

    public function test_eliminar_una_excepcion_de_local(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $excepcion = Excepcion::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/excepciones/{$excepcion->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('excepcion', ['id' => $excepcion->id]);
    }

    public function test_eliminar_una_excepcion_de_profesional(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        $excepcion = Excepcion::factory()->deProfesional($profesional->id)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/excepciones/{$excepcion->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('excepcion', ['id' => $excepcion->id]);
    }

    /** El profesional puede bloquear su propio horario (§3.2), no solo quien administra el local. */
    public function test_el_propio_profesional_puede_bloquear_su_propio_horario(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $cuenta = Usuario::factory()->create();
        $profesional = Profesional::factory()->conCuenta()->create(['usuario_id' => $cuenta->id]);
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);

        $this->withHeader('Authorization', "Bearer {$cuenta->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/excepciones", [
                'fecha_inicio' => now()->addDay()->toIso8601String(),
                'fecha_fin' => now()->addDays(2)->toIso8601String(),
                'motivo' => 'personal',
            ])
            ->assertCreated()
            ->assertJsonPath('profesional_id', $profesional->id);
    }

    public function test_un_profesional_no_puede_bloquear_el_horario_de_otro(): void
    {
        $cuenta = Usuario::factory()->create();
        Profesional::factory()->conCuenta()->create(['usuario_id' => $cuenta->id]);
        $colega = Profesional::factory()->create();

        $this->withHeader('Authorization', "Bearer {$cuenta->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/profesionales/{$colega->id}/excepciones", [
                'fecha_inicio' => now()->addDay()->toIso8601String(),
                'fecha_fin' => now()->addDays(2)->toIso8601String(),
                'motivo' => 'personal',
            ])
            ->assertForbidden();
    }

    public function test_un_recepcionista_no_puede_crear_excepciones(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/excepciones", [
                'fecha_inicio' => now()->addDay()->toIso8601String(),
                'fecha_fin' => now()->addDays(2)->toIso8601String(),
                'motivo' => 'feriado',
            ])
            ->assertForbidden();
    }
}
