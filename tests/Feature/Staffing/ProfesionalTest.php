<?php

namespace Tests\Feature\Staffing;

use App\Models\Asignacion;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

class ProfesionalTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_crear_un_profesional_crea_tambien_su_primera_asignacion(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/profesionales", [
                'nombre' => 'Kevin', 'alias' => 'Kevin el Fade',
                'rol' => 'barbero', 'modalidad' => 'empleado', 'comision_pct' => 50,
            ])
            ->assertCreated()
            ->assertJsonPath('nombre', 'Kevin')
            ->assertJsonPath('independiente', false)
            ->assertJsonPath('perfil_publico', true)
            ->assertJsonPath('traslado_min', 30);

        $this->assertDatabaseHas('asignacion', [
            'local_id' => $local->id,
            'profesional_id' => $respuesta->json('id'),
            'rol' => 'barbero',
            'comision_pct' => 50,
        ]);
    }

    public function test_listar_profesionales_del_local_solo_trae_los_con_asignacion_vigente(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $otroLocal = Local::factory()->create();

        $enEsteLocal = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $enEsteLocal->id]);

        $enOtroLocal = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $otroLocal->id, 'profesional_id' => $enOtroLocal->id]);

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/locales/{$local->id}/profesionales")
            ->assertOk()
            ->assertJsonCount(1);

        $this->assertSame($enEsteLocal->id, $respuesta->json('0.id'));
    }

    public function test_un_recepcionista_no_puede_dar_de_alta_profesionales(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/profesionales", [
                'nombre' => 'X', 'rol' => 'barbero', 'modalidad' => 'empleado', 'comision_pct' => 50,
            ])
            ->assertForbidden();
    }

    public function test_un_dueño_que_no_lo_emplea_no_puede_ver_ni_editar_al_profesional(): void
    {
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['profesional_id' => $profesional->id]); // en OTRO local

        $extraño = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$extraño->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/profesionales/{$profesional->id}")
            ->assertForbidden();
    }

    public function test_quien_lo_emplea_puede_ver_y_editar_al_profesional(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/profesionales/{$profesional->id}")
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/profesionales/{$profesional->id}", ['alias' => 'El Nuevo Alias'])
            ->assertOk()
            ->assertJsonPath('alias', 'El Nuevo Alias');
    }
}
