<?php

namespace Tests\Feature\Scheduling;

use App\Models\Profesional;
use App\Models\ServicioLocal;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/** Walk-in (§4.7, §5.6): ocupa slot igual que uno de la app, `canal = 'local'`. */
class WalkInTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    private function headers(string $token): array
    {
        return ['Authorization' => "Bearer {$token}", 'Idempotency-Key' => (string) Str::uuid()];
    }

    public function test_el_propietario_registra_un_walkin_con_nombre_y_telefono(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id, 'duracion_min' => 30, 'buffer_min' => 0]);
        $profesional = Profesional::factory()->create();

        $this->withHeaders($this->headers($token))
            ->postJson("/api/v1/locales/{$local->id}/citas/walk-in", [
                'nombre' => 'Cliente Walk-in', 'telefono' => '0990001122',
                'profesional_id' => $profesional->id, 'servicios' => [$servicio->id],
                'inicio' => now()->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('estado', 'confirmada')
            ->assertJsonPath('canal', 'local');

        $this->assertDatabaseHas('usuario', ['telefono' => '0990001122', 'password_hash' => null]);
    }

    public function test_un_recepcionista_tambien_puede_registrar_walkins(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id, 'duracion_min' => 30, 'buffer_min' => 0]);
        $profesional = Profesional::factory()->create();

        $this->withHeaders($this->headers($token))
            ->postJson("/api/v1/locales/{$local->id}/citas/walk-in", [
                'nombre' => 'Otro Cliente', 'telefono' => '0990001133',
                'profesional_id' => $profesional->id, 'servicios' => [$servicio->id],
                'inicio' => now()->toIso8601String(),
            ])
            ->assertCreated();
    }

    public function test_reusa_el_usuario_existente_si_el_telefono_ya_esta_registrado(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $existente = Usuario::factory()->create(['telefono' => '0990001144']);
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id, 'duracion_min' => 30, 'buffer_min' => 0]);
        $profesional = Profesional::factory()->create();

        $this->withHeaders($this->headers($token))
            ->postJson("/api/v1/locales/{$local->id}/citas/walk-in", [
                'nombre' => 'Nombre Distinto', 'telefono' => '0990001144',
                'profesional_id' => $profesional->id, 'servicios' => [$servicio->id],
                'inicio' => now()->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('cliente_id', $existente->id);

        $this->assertSame(1, Usuario::where('telefono', '0990001144')->count());
    }

    public function test_un_extrano_no_puede_registrar_walkins(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $extrano = Usuario::factory()->create();
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id]);
        $profesional = Profesional::factory()->create();

        $this->withHeaders($this->headers($extrano->createToken('t')->plainTextToken))
            ->postJson("/api/v1/locales/{$local->id}/citas/walk-in", [
                'nombre' => 'X', 'telefono' => '0990001155',
                'profesional_id' => $profesional->id, 'servicios' => [$servicio->id],
                'inicio' => now()->toIso8601String(),
            ])
            ->assertForbidden();
    }
}
