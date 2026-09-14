<?php

namespace Tests\Feature\Scheduling;

use App\Models\Cita;
use App\Models\Espera;
use App\Models\ServicioLocal;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/** Lista de espera (§4.7): convierte cancelaciones en oportunidades. */
class EsperaTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_un_cliente_se_anota_en_la_lista_de_espera(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id]);
        $cliente = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/locales/{$local->id}/esperas", [
                'servicio_local_id' => $servicio->id,
                'fecha_deseada' => now()->addDays(2)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('estado', 'activa');
    }

    /** Cancelar una cita libera el cupo: la espera que calza pasa a "notificada" (§4.7). */
    public function test_cancelar_una_cita_marca_como_notificada_a_quien_esperaba_ese_cupo(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id]);
        $fecha = now()->addDays(2);

        $cita = Cita::factory()->create([
            'local_id' => $local->id,
            'inicio' => $fecha->copy()->setTime(10, 0),
            'fin' => $fecha->copy()->setTime(11, 0),
        ]);
        $cita->items()->create([
            'servicio_local_id' => $servicio->id, 'precio' => 10, 'duracion_min' => 60, 'comisionable' => true, 'comision_pct' => 50,
        ]);

        $espera = Espera::factory()->create([
            'local_id' => $local->id, 'servicio_local_id' => $servicio->id,
            'fecha_deseada' => $fecha->toDateString(), 'profesional_id' => null, 'estado' => 'activa',
        ]);

        $this->withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/citas/{$cita->id}/cancelar")
            ->assertOk();

        $this->assertDatabaseHas('espera', ['id' => $espera->id, 'estado' => 'notificada']);
    }

    public function test_listar_esperas_requiere_ser_del_local(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $extrano = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$extrano->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/locales/{$local->id}/esperas")
            ->assertForbidden();
    }
}
