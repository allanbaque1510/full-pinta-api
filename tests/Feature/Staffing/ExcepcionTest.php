<?php

namespace Tests\Feature\Staffing;

use App\Models\Asignacion;
use App\Models\Excepcion;
use App\Models\Profesional;
use App\Models\Recurso;
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
