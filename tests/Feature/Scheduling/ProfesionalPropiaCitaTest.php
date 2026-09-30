<?php

namespace Tests\Feature\Scheduling;

use App\Models\Cita;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * El profesional puede ver y gestionar sus propias citas (§3.2) — antes
 * `CitaPolicy` solo reconocía "cliente dueño" o "staff del local"
 * (`negocio_miembro`), nunca "soy el profesional asignado". Depende de que
 * el profesional tenga cuenta vinculada (`Profesional.usuario_id`).
 */
class ProfesionalPropiaCitaTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_el_profesional_ve_su_propia_cita(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $cuenta = Usuario::factory()->create();
        $profesional = Profesional::factory()->conCuenta()->create(['usuario_id' => $cuenta->id]);
        $cita = Cita::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);

        $this->withHeader('Authorization', "Bearer {$cuenta->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/citas/{$cita->id}")
            ->assertOk();
    }

    public function test_el_profesional_no_ve_la_cita_de_un_colega(): void
    {
        $cuenta = Usuario::factory()->create();
        Profesional::factory()->conCuenta()->create(['usuario_id' => $cuenta->id]);
        $cita = Cita::factory()->create(); // profesional distinto

        $this->withHeader('Authorization', "Bearer {$cuenta->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/citas/{$cita->id}")
            ->assertForbidden();
    }

    public function test_el_profesional_completa_su_propia_cita(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $cuenta = Usuario::factory()->create();
        $profesional = Profesional::factory()->conCuenta()->create(['usuario_id' => $cuenta->id]);
        $cita = Cita::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id, 'estado' => 'en_curso']);

        $this->withHeader('Authorization', "Bearer {$cuenta->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$cita->id}/completar")
            ->assertOk()
            ->assertJsonPath('estado', 'completada');
    }

    public function test_el_profesional_no_puede_completar_la_cita_de_un_colega(): void
    {
        $cuenta = Usuario::factory()->create();
        Profesional::factory()->conCuenta()->create(['usuario_id' => $cuenta->id]);
        $cita = Cita::factory()->create(['estado' => 'en_curso']); // profesional distinto

        $this->withHeader('Authorization', "Bearer {$cuenta->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$cita->id}/completar")
            ->assertForbidden();
    }

    public function test_el_profesional_marca_no_show_en_su_propia_cita(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $cuenta = Usuario::factory()->create();
        $profesional = Profesional::factory()->conCuenta()->create(['usuario_id' => $cuenta->id]);
        $cita = Cita::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id, 'estado' => 'confirmada']);

        $this->withHeader('Authorization', "Bearer {$cuenta->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$cita->id}/no-show")
            ->assertOk()
            ->assertJsonPath('estado', 'no_show');
    }

    public function test_get_mis_citas_profesional_devuelve_solo_las_suyas_en_cualquier_local(): void
    {
        [, , , $localA] = $this->propietarioConLocal();
        $localB = Local::factory()->create();
        $cuenta = Usuario::factory()->create();
        $profesional = Profesional::factory()->conCuenta()->create(['usuario_id' => $cuenta->id]);

        // Horarios distintos: el mismo profesional no puede tener dos citas
        // traslapadas, ni siquiera en locales distintos (§4.11 #2).
        Cita::factory()->entre(now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0))
            ->create(['local_id' => $localA->id, 'profesional_id' => $profesional->id]);
        Cita::factory()->entre(now()->addDay()->setTime(12, 0), now()->addDay()->setTime(13, 0))
            ->create(['local_id' => $localB->id, 'profesional_id' => $profesional->id]);
        Cita::factory()->create(['local_id' => $localA->id]); // de un colega

        $this->withHeader('Authorization', "Bearer {$cuenta->createToken('t')->plainTextToken}")
            ->getJson('/api/v1/mis-citas-profesional')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_get_mis_citas_profesional_sin_cuenta_de_profesional_vinculada_es_403(): void
    {
        $cliente = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->getJson('/api/v1/mis-citas-profesional')
            ->assertForbidden()
            ->assertJsonPath('codigo', 'no_es_profesional');
    }
}
