<?php

namespace Tests\Feature\Reviews;

use App\Models\Cita;
use App\Models\Local;
use App\Models\Resena;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * Reseñas (§4.8): solo una cita `completada`, dentro de 14 días, una por cita.
 */
class ResenaTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_el_cliente_dueno_puede_resenar_una_cita_completada(): void
    {
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->completada()->create(['cliente_id' => $cliente->id]);

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$cita->id}/resenas", [
                'puntaje_local' => 5, 'puntaje_profesional' => 4, 'comentario' => 'Excelente atención',
            ])
            ->assertCreated()
            ->assertJsonPath('cita_id', $cita->id)
            ->assertJsonPath('local_id', $cita->local_id)
            ->assertJsonPath('puntaje_local', 5)
            ->assertJsonPath('estado', 'publicada');
    }

    public function test_no_se_puede_resenar_una_cita_que_no_esta_completada(): void
    {
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->create(['cliente_id' => $cliente->id]); // nace confirmada

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$cita->id}/resenas", ['puntaje_local' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cita_id');
    }

    public function test_no_se_puede_resenar_fuera_de_la_ventana_de_14_dias(): void
    {
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->completada()->create([
            'cliente_id' => $cliente->id,
            'completada_at' => now()->subDays(20),
        ]);

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$cita->id}/resenas", ['puntaje_local' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cita_id');
    }

    public function test_no_se_puede_resenar_dos_veces_la_misma_cita(): void
    {
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->completada()->create(['cliente_id' => $cliente->id]);
        Resena::factory()->create(['cita_id' => $cita->id]);

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$cita->id}/resenas", ['puntaje_local' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cita_id');
    }

    public function test_un_extrano_no_puede_resenar_la_cita_de_otro(): void
    {
        $cita = Cita::factory()->completada()->create();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$cita->id}/resenas", ['puntaje_local' => 5])
            ->assertForbidden();
    }

    public function test_el_staff_del_local_puede_listar_las_resenas_incluyendo_no_publicadas(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cita = Cita::factory()->completada()->create(['local_id' => $local->id]);
        Resena::factory()->create(['cita_id' => $cita->id, 'estado' => 'oculta']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/locales/{$local->id}/resenas")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.estado', 'oculta');
    }

    public function test_un_extrano_no_puede_listar_resenas_del_local(): void
    {
        $local = Local::factory()->create();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/locales/{$local->id}/resenas")
            ->assertForbidden();
    }

    public function test_el_staff_puede_responder_una_resena(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cita = Cita::factory()->completada()->create(['local_id' => $local->id]);
        $resena = Resena::factory()->create(['cita_id' => $cita->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/resenas/{$resena->id}/responder", ['respuesta_local' => 'Gracias por tu visita'])
            ->assertOk()
            ->assertJsonPath('respuesta_local', 'Gracias por tu visita')
            ->assertJsonPath('respuesta_at', fn ($valor) => $valor !== null);
    }

    public function test_un_extrano_no_puede_responder_una_resena(): void
    {
        $resena = Resena::factory()->create();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/resenas/{$resena->id}/responder", ['respuesta_local' => 'x'])
            ->assertForbidden();
    }
}
