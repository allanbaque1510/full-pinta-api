<?php

namespace Tests\Feature\Staffing;

use App\Models\Asignacion;
use App\Models\Habilidad;
use App\Models\Profesional;
use App\Models\ServicioLocal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * `Habilidad` (§4.6): qué servicios puede atender un profesional — si el
 * cliente agenda uñas y el sistema le asigna al barbero que solo hace fades,
 * hay problema el día uno.
 */
class HabilidadTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_el_propietario_puede_asignar_una_habilidad(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/habilidades", [
                'servicio_local_id' => $servicio->id,
            ])
            ->assertCreated()
            ->assertJsonPath('servicio_local_id', $servicio->id);
    }

    public function test_no_se_puede_asignar_la_misma_habilidad_dos_veces(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id]);
        Habilidad::factory()->create(['profesional_id' => $profesional->id, 'servicio_local_id' => $servicio->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/habilidades", [
                'servicio_local_id' => $servicio->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('servicio_local_id');
    }

    public function test_eliminar_una_habilidad(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id]);
        $habilidad = Habilidad::factory()->create(['profesional_id' => $profesional->id, 'servicio_local_id' => $servicio->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/profesionales/{$profesional->id}/habilidades/{$habilidad->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('habilidad', ['id' => $habilidad->id]);
    }

    public function test_un_recepcionista_no_puede_asignar_habilidades(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/habilidades", [
                'servicio_local_id' => $servicio->id,
            ])
            ->assertForbidden();
    }
}
