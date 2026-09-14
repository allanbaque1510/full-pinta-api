<?php

namespace Tests\Feature\Staffing;

use App\Models\Asignacion;
use App\Models\Profesional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * Un profesional que ya existe se suma a un local nuevo (§4.6: "Kevin trabaja
 * en dos locales") sin recrear su perfil.
 */
class AsignacionTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_sumar_un_profesional_existente_a_un_segundo_local(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $kevin = Profesional::factory()->create(['nombre' => 'Kevin']);
        Asignacion::factory()->create(['profesional_id' => $kevin->id]); // ya trabaja en otro local

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/asignaciones", [
                'profesional_id' => $kevin->id, 'rol' => 'barbero', 'modalidad' => 'renta_silla', 'comision_pct' => 60,
            ])
            ->assertCreated()
            ->assertJsonPath('local_id', $local->id)
            ->assertJsonPath('profesional_id', $kevin->id);

        $this->assertSame(2, $kevin->asignaciones()->count());
    }

    public function test_terminar_una_asignacion_le_pone_fecha_de_fin_no_la_borra(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/asignaciones/{$asignacion->id}/terminar")
            ->assertOk()
            ->assertJsonPath('hasta', now()->toDateString());

        $this->assertDatabaseHas('asignacion', ['id' => $asignacion->id]);
    }

    public function test_un_recepcionista_no_puede_sumar_profesionales(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);
        $profesional = Profesional::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/asignaciones", [
                'profesional_id' => $profesional->id, 'rol' => 'barbero', 'modalidad' => 'empleado', 'comision_pct' => 50,
            ])
            ->assertForbidden();
    }
}
