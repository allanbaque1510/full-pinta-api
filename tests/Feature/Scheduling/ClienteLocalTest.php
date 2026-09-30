<?php

namespace Tests\Feature\Scheduling;

use App\Models\Asignacion;
use App\Models\Cita;
use App\Models\ClienteLocal;
use App\Models\ClientePerfil;
use App\Models\Profesional;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * Ficha del cliente en un local (§4.7): continuidad de servicio, no
 * calificación. El total de visitas se calcula en vivo contra `cita`, no se
 * guarda como contador (revisión de base de datos, 2026-09-29).
 */
class ClienteLocalTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_la_ficha_calcula_el_resumen_de_visitas_en_vivo(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cliente = Usuario::factory()->create();

        Cita::factory()->completada()->create([
            'local_id' => $local->id, 'cliente_id' => $cliente->id,
            'inicio' => now()->subMonths(2), 'fin' => now()->subMonths(2)->addHour(),
        ]);
        Cita::factory()->completada()->create([
            'local_id' => $local->id, 'cliente_id' => $cliente->id,
            'inicio' => now()->subWeek(), 'fin' => now()->subWeek()->addHour(),
        ]);
        // Un hold sin completar, y una cita completada en OTRO local: ninguna cuenta.
        Cita::factory()->create(['local_id' => $local->id, 'cliente_id' => $cliente->id]);
        Cita::factory()->completada()->create(['cliente_id' => $cliente->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/locales/{$local->id}/clientes/{$cliente->id}")
            ->assertOk()
            ->assertJsonPath('total_citas', 2)
            ->assertJsonPath('nota', null);
    }

    public function test_la_ficha_muestra_la_confiabilidad_de_toda_la_plataforma(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cliente = Usuario::factory()->create();
        ClientePerfil::factory()->create([
            'usuario_id' => $cliente->id, 'no_shows' => 3,
            'cancelaciones_tardias' => 1, 'requiere_confirmacion' => true,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/locales/{$local->id}/clientes/{$cliente->id}")
            ->assertOk()
            ->assertJsonPath('no_shows', 3)
            ->assertJsonPath('cancelaciones_tardias', 1)
            ->assertJsonPath('requiere_confirmacion', true);
    }

    public function test_la_ficha_no_falla_si_el_cliente_nunca_tuvo_fila_en_cliente_perfil(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cliente = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/locales/{$local->id}/clientes/{$cliente->id}")
            ->assertOk()
            ->assertJsonPath('no_shows', 0)
            ->assertJsonPath('cancelaciones_tardias', 0)
            ->assertJsonPath('requiere_confirmacion', false);
    }

    public function test_actualizar_crea_la_fila_si_no_existe(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cliente = Usuario::factory()->create();

        $this->assertDatabaseCount('cliente_local', 0);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/locales/{$local->id}/clientes/{$cliente->id}", ['nota' => 'Fade 2 a los lados'])
            ->assertOk()
            ->assertJsonPath('nota', 'Fade 2 a los lados');

        $this->assertDatabaseHas('cliente_local', [
            'usuario_id' => $cliente->id, 'local_id' => $local->id, 'nota' => 'Fade 2 a los lados',
        ]);
    }

    public function test_rechaza_un_profesional_preferido_que_no_trabaja_en_el_local(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cliente = Usuario::factory()->create();
        $profesionalDeOtroLocal = Profesional::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/locales/{$local->id}/clientes/{$cliente->id}", [
                'profesional_preferido_id' => $profesionalDeOtroLocal->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profesional_preferido_id');
    }

    public function test_acepta_un_profesional_preferido_con_asignacion_vigente(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cliente = Usuario::factory()->create();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/locales/{$local->id}/clientes/{$cliente->id}", [
                'profesional_preferido_id' => $asignacion->profesional_id,
            ])
            ->assertOk()
            ->assertJsonPath('profesional_preferido_id', $asignacion->profesional_id);
    }

    public function test_un_extrano_no_puede_ver_ni_editar_la_ficha(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $cliente = Usuario::factory()->create();
        $extrano = Usuario::factory()->create();
        $token = $extrano->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/locales/{$local->id}/clientes/{$cliente->id}")
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/locales/{$local->id}/clientes/{$cliente->id}", ['nota' => 'nope'])
            ->assertForbidden();
    }

    public function test_bandeja_mensual_agrupa_las_visitas_del_mes(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $clienteA = Usuario::factory()->create(['nombre' => 'Ana Pérez']);
        $clienteB = Usuario::factory()->create(['nombre' => 'Beto Ruiz']);
        $mesConsultado = now()->startOfMonth()->addDays(5);

        Cita::factory()->completada()->create([
            'local_id' => $local->id, 'cliente_id' => $clienteA->id,
            'inicio' => $mesConsultado, 'fin' => $mesConsultado->copy()->addHour(),
        ]);
        Cita::factory()->completada()->create([
            'local_id' => $local->id, 'cliente_id' => $clienteA->id,
            'inicio' => $mesConsultado->copy()->addDay(), 'fin' => $mesConsultado->copy()->addDay()->addHour(),
        ]);
        Cita::factory()->completada()->create([
            'local_id' => $local->id, 'cliente_id' => $clienteB->id,
            'inicio' => $mesConsultado, 'fin' => $mesConsultado->copy()->addHour(),
        ]);
        // Fuera del mes consultado: no debe contar.
        Cita::factory()->completada()->create([
            'local_id' => $local->id, 'cliente_id' => $clienteA->id,
            'inicio' => $mesConsultado->copy()->subMonths(2), 'fin' => $mesConsultado->copy()->subMonths(2)->addHour(),
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/locales/{$local->id}/clientes?mes={$mesConsultado->format('Y-m')}")
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.cliente_id', $clienteA->id)
            ->assertJsonPath('0.visitas_en_el_mes', 2)
            ->assertJsonPath('1.cliente_id', $clienteB->id)
            ->assertJsonPath('1.visitas_en_el_mes', 1);
    }

    public function test_completar_una_cita_no_crea_fila_en_cliente_local(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->create(['local_id' => $local->id, 'cliente_id' => $cliente->id, 'estado' => 'en_curso']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/citas/{$cita->id}/completar")
            ->assertOk();

        $this->assertDatabaseCount('cliente_local', 0);
        $this->assertFalse(ClienteLocal::where('usuario_id', $cliente->id)->where('local_id', $local->id)->exists());
    }
}
