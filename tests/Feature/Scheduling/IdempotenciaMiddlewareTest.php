<?php

namespace Tests\Feature\Scheduling;

use App\Models\Cita;
use App\Models\ClientePerfil;
use App\Models\Local;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * El middleware de idempotencia (§12.4), probado directo sobre un endpoint
 * real que lo exige (`cancelar`) — más honesto que invocar la clase a mano,
 * porque también ejercita el registro/lectura reales contra Postgres.
 */
class IdempotenciaMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function crearCitaYToken(): array
    {
        $cliente = Usuario::factory()->create();
        ClientePerfil::factory()->create(['usuario_id' => $cliente->id]);
        $local = Local::factory()->create();
        $cita = Cita::factory()->create([
            'cliente_id' => $cliente->id, 'local_id' => $local->id,
            'inicio' => now()->addDays(3), 'fin' => now()->addDays(3)->addHour(),
        ]);

        return [$cita, $cliente->createToken('t')->plainTextToken];
    }

    public function test_falta_el_header_devuelve_400(): void
    {
        [$cita, $token] = $this->crearCitaYToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/citas/{$cita->id}/cancelar")
            ->assertStatus(400)
            ->assertJsonPath('codigo', 'idempotency_key_requerida');
    }

    public function test_reintentar_con_la_misma_clave_y_el_mismo_cuerpo_devuelve_la_respuesta_guardada(): void
    {
        [$cita, $token] = $this->crearCitaYToken();
        $clave = (string) Str::uuid();
        $headers = ['Authorization' => "Bearer {$token}", 'Idempotency-Key' => $clave];

        $primera = $this->withHeaders($headers)
            ->postJson("/api/v1/citas/{$cita->id}/cancelar", ['motivo' => 'Ya no puedo ir'])
            ->assertOk();

        $segunda = $this->withHeaders($headers)
            ->postJson("/api/v1/citas/{$cita->id}/cancelar", ['motivo' => 'Ya no puedo ir'])
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');

        // assertEquals, no assertSame: el orden de las llaves no importa, solo
        // que sea el mismo contenido guardado (round-trip json_decode/encode).
        $this->assertEquals($primera->json(), $segunda->json());
        $this->assertSame(1, DB::table('cita_bitacora')->where('cita_id', $cita->id)->count());
    }

    /**
     * Revisión de base de datos 2026-09-28: antes solo se validaba
     * usuario+endpoint, nunca el contenido — un reintento con datos distintos
     * bajo la misma clave recibía en silencio la respuesta del primer intento.
     */
    public function test_reusar_la_clave_con_un_cuerpo_distinto_devuelve_422(): void
    {
        [$cita, $token] = $this->crearCitaYToken();
        $clave = (string) Str::uuid();
        $headers = ['Authorization' => "Bearer {$token}", 'Idempotency-Key' => $clave];

        $this->withHeaders($headers)
            ->postJson("/api/v1/citas/{$cita->id}/cancelar", ['motivo' => 'Motivo original'])
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson("/api/v1/citas/{$cita->id}/cancelar", ['motivo' => 'Motivo distinto'])
            ->assertStatus(422)
            ->assertJsonPath('codigo', 'idempotency_key_conflicto_payload');
    }
}
