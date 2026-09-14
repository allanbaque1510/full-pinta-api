<?php

namespace Tests\Feature\Scheduling;

use App\Models\Cita;
use App\Models\ClientePerfil;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\ServicioLocal;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Reagendar NO es cancelar + crear (§4.7, §6): no penaliza al cliente. */
class ReagendarCitaTest extends TestCase
{
    use RefreshDatabase;

    public function test_reagendar_crea_una_cita_nueva_enlazada_y_no_penaliza(): void
    {
        $cliente = Usuario::factory()->create();
        ClientePerfil::factory()->create(['usuario_id' => $cliente->id]);
        $local = Local::factory()->create();
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id, 'duracion_min' => 30, 'buffer_min' => 0]);
        $profesional = Profesional::factory()->create();
        $citaVieja = Cita::factory()->create([
            'local_id' => $local->id, 'cliente_id' => $cliente->id, 'profesional_id' => $profesional->id,
        ]);

        $nuevoInicio = now()->addDays(2)->setTime(11, 0)->toIso8601String();

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$citaVieja->id}/reagendar", [
                'profesional_id' => $profesional->id,
                'servicios' => [$servicio->id],
                'inicio' => $nuevoInicio,
            ])
            ->assertCreated()
            ->assertJsonPath('reagendada_de_id', $citaVieja->id)
            ->assertJsonPath('estado', 'reservada');

        $this->assertDatabaseHas('cita', ['id' => $citaVieja->id, 'estado' => 'reagendada']);
        $this->assertDatabaseHas('cliente_perfil', ['usuario_id' => $cliente->id, 'cancelaciones_tardias' => 0]);
        $this->assertDatabaseHas('cita_evento', [
            'cita_id' => $citaVieja->id, 'estado_nuevo' => 'reagendada',
        ]);
    }

    public function test_no_se_puede_reagendar_una_cita_terminal(): void
    {
        $cliente = Usuario::factory()->create();
        $citaVieja = Cita::factory()->completada()->create(['cliente_id' => $cliente->id]);
        $servicio = ServicioLocal::factory()->create();
        $profesional = Profesional::factory()->create();

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$citaVieja->id}/reagendar", [
                'profesional_id' => $profesional->id,
                'servicios' => [$servicio->id],
                'inicio' => now()->addDay()->toIso8601String(),
            ])
            ->assertUnprocessable();
    }

    public function test_un_extrano_no_puede_reagendar_la_cita_de_otro(): void
    {
        $citaVieja = Cita::factory()->create();
        $otro = Usuario::factory()->create();
        $servicio = ServicioLocal::factory()->create();
        $profesional = Profesional::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$citaVieja->id}/reagendar", [
                'profesional_id' => $profesional->id,
                'servicios' => [$servicio->id],
                'inicio' => now()->addDay()->toIso8601String(),
            ])
            ->assertForbidden();
    }
}
