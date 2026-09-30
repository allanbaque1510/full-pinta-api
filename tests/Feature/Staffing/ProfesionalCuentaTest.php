<?php

namespace Tests\Feature\Staffing;

use App\Models\Asignacion;
use App\Models\Profesional;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * Vínculo cuenta↔profesional (§4.6): antes no existía ningún flujo HTTP que
 * lo creara — `Profesional.usuario_id` (nullable a propósito) nunca se
 * asignaba fuera de tests. Sin este vínculo, un barbero no puede iniciar
 * sesión y ver su propia agenda/comisiones, aunque el resto del código
 * (`ContextoAcceso`, `ResolverContexto`) ya lo esperara.
 */
class ProfesionalCuentaTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_crear_un_profesional_con_telefono_lo_vincula_de_una_vez(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cuenta = Usuario::factory()->create(['telefono' => '0991112222']);

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/profesionales", [
                'nombre' => 'Kevin', 'rol' => 'barbero', 'modalidad' => 'empleado',
                'comision_pct' => 50, 'telefono' => '0991112222',
            ])
            ->assertCreated()
            ->assertJsonPath('tiene_cuenta_propia', true);

        $this->assertDatabaseHas('profesional', ['id' => $respuesta->json('id'), 'usuario_id' => $cuenta->id]);
    }

    public function test_crear_un_profesional_sin_telefono_nace_sin_cuenta(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/profesionales", [
                'nombre' => 'Kevin', 'rol' => 'barbero', 'modalidad' => 'empleado', 'comision_pct' => 50,
            ])
            ->assertCreated()
            ->assertJsonPath('tiene_cuenta_propia', false);
    }

    public function test_vincular_cuenta_a_un_profesional_ya_existente(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        $cuenta = Usuario::factory()->create(['telefono' => '0993334444']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/vincular-cuenta", ['telefono' => '0993334444'])
            ->assertOk()
            ->assertJsonPath('tiene_cuenta_propia', true);

        $this->assertDatabaseHas('profesional', ['id' => $profesional->id, 'usuario_id' => $cuenta->id]);
    }

    public function test_vincular_rechaza_un_telefono_no_registrado(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/vincular-cuenta", ['telefono' => '0999998888'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('telefono');
    }

    public function test_vincular_rechaza_una_cuenta_ya_vinculada_a_otro_profesional(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cuenta = Usuario::factory()->create(['telefono' => '0995556666']);
        Profesional::factory()->conCuenta()->create(['usuario_id' => $cuenta->id]);
        $otroProfesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $otroProfesional->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/profesionales/{$otroProfesional->id}/vincular-cuenta", ['telefono' => '0995556666'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('telefono');
    }

    public function test_el_propio_profesional_no_puede_vincularse_a_si_mismo(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $cuenta = Usuario::factory()->create();
        $profesional = Profesional::factory()->conCuenta()->create(['usuario_id' => $cuenta->id]);
        $otro = Usuario::factory()->create(['telefono' => '0997778888']);

        $this->withHeader('Authorization', "Bearer {$cuenta->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/vincular-cuenta", ['telefono' => '0997778888'])
            ->assertForbidden();
    }

    public function test_un_extrano_no_puede_vincular_cuenta(): void
    {
        $profesional = Profesional::factory()->create();
        $extrano = Usuario::factory()->create();
        Usuario::factory()->create(['telefono' => '0991230000']);

        $this->withHeader('Authorization', "Bearer {$extrano->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/vincular-cuenta", ['telefono' => '0991230000'])
            ->assertForbidden();
    }
}
