<?php

namespace Tests\Feature\Directory;

use App\Models\HorarioLocal;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * Varias filas por día permiten partir jornada — mañana/tarde (§4.4).
 */
class HorarioLocalTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_el_propietario_puede_crear_un_horario(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/horarios", [
                'dia_semana' => 1, 'abre' => '09:00', 'cierra' => '19:00',
            ])
            ->assertCreated()
            ->assertJsonPath('dia_semana', 1)
            ->assertJsonPath('abre', '09:00')
            ->assertJsonPath('cierra', '19:00');
    }

    public function test_rechaza_un_horario_con_cierre_antes_que_apertura(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/horarios", [
                'dia_semana' => 1, 'abre' => '19:00', 'cierra' => '09:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cierra');
    }

    public function test_un_recepcionista_no_puede_crear_horarios(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/horarios", [
                'dia_semana' => 1, 'abre' => '09:00', 'cierra' => '19:00',
            ])
            ->assertForbidden();
    }

    public function test_actualizar_solo_la_hora_de_cierre_compara_bien_contra_la_apertura_guardada(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $horario = HorarioLocal::factory()->create(['local_id' => $local->id, 'abre' => '09:00', 'cierra' => '18:00']);

        // Solo se manda `cierra`; `abre` viene de la fila ya guardada
        // ("09:00:00" con segundos) — el servicio tiene que normalizar el
        // formato antes de comparar.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/horarios/{$horario->id}", ['dia_semana' => 1, 'cierra' => '20:00'])
            ->assertOk()
            ->assertJsonPath('cierra', '20:00');
    }

    public function test_eliminar_un_horario_lo_borra_de_verdad(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $horario = HorarioLocal::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/horarios/{$horario->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('horario_local', ['id' => $horario->id]);
    }

    public function test_un_extrano_no_puede_ver_los_horarios(): void
    {
        [, , , $local] = $this->propietarioConLocal();

        $otro = Usuario::factory()->create();
        $token = $otro->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/locales/{$local->id}/horarios")
            ->assertForbidden();
    }
}
