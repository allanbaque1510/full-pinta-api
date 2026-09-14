<?php

namespace Tests\Feature\Notifications;

use App\Models\DeviceToken;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ciclo de vida del token de dispositivo (§11.7, §11.9). */
class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_un_token_nuevo(): void
    {
        $usuario = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$usuario->createToken('t')->plainTextToken}")
            ->postJson('/api/v1/dispositivos', [
                'token' => 'fcm-token-abc', 'plataforma' => 'android', 'app_version' => '1.2.0',
            ])
            ->assertCreated()
            ->assertJsonPath('plataforma', 'android')
            ->assertJsonPath('activo', true);

        $this->assertDatabaseHas('device_token', ['usuario_id' => $usuario->id, 'token' => 'fcm-token-abc']);
    }

    public function test_registrar_el_mismo_token_dos_veces_lo_actualiza_en_vez_de_duplicarlo(): void
    {
        $usuario = Usuario::factory()->create();
        $token = "Bearer {$usuario->createToken('t')->plainTextToken}";

        $this->withHeader('Authorization', $token)
            ->postJson('/api/v1/dispositivos', ['token' => 'mismo-token', 'plataforma' => 'android'])
            ->assertCreated();

        $this->withHeader('Authorization', $token)
            ->postJson('/api/v1/dispositivos', ['token' => 'mismo-token', 'plataforma' => 'android', 'app_version' => '2.0.0'])
            ->assertCreated()
            ->assertJsonPath('app_version', '2.0.0');

        $this->assertDatabaseCount('device_token', 1);
    }

    public function test_un_usuario_puede_borrar_su_propio_token(): void
    {
        $usuario = Usuario::factory()->create();
        $deviceToken = DeviceToken::factory()->create(['usuario_id' => $usuario->id]);

        $this->withHeader('Authorization', "Bearer {$usuario->createToken('t')->plainTextToken}")
            ->deleteJson("/api/v1/dispositivos/{$deviceToken->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('device_token', ['id' => $deviceToken->id]);
    }

    public function test_un_extrano_no_puede_borrar_el_token_de_otro(): void
    {
        $deviceToken = DeviceToken::factory()->create();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->deleteJson("/api/v1/dispositivos/{$deviceToken->id}")
            ->assertForbidden();
    }
}
