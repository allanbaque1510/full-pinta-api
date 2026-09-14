<?php

namespace Tests\Feature\Notifications;

use App\Models\DeviceToken;
use App\Models\Notificacion;
use App\Models\PlantillaWhatsapp;
use App\Models\Usuario;
use App\Modules\Notifications\Application\Contracts\EnviadorPush;
use App\Modules\Notifications\Application\Contracts\EnviadorWebSocket;
use App\Modules\Notifications\Application\Contracts\EnviadorWhatsApp;
use App\Modules\Notifications\Application\FakeEnviadorPush;
use App\Modules\Notifications\Application\FakeEnviadorWebSocket;
use App\Modules\Notifications\Application\FakeEnviadorWhatsApp;
use App\Modules\Notifications\Application\NotificacionService;
use App\Modules\Notifications\Jobs\EnviarNotificacionesProgramadas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnviarNotificacionesProgramadasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeEnviadorPush::reset();
        FakeEnviadorWhatsApp::reset();
        FakeEnviadorWebSocket::reset();
    }

    public function test_envia_una_notificacion_push_pendiente_y_la_marca_enviada(): void
    {
        $usuario = Usuario::factory()->create();
        DeviceToken::factory()->create(['usuario_id' => $usuario->id, 'activo' => true]);
        $notificacion = Notificacion::factory()->create(['usuario_id' => $usuario->id, 'programada_para' => now()->subMinute()]);

        $this->ejecutar();

        $this->assertDatabaseHas('notificacion', ['id' => $notificacion->id, 'estado' => 'enviada']);
        $this->assertCount(1, FakeEnviadorPush::enviados());
    }

    public function test_falla_si_el_usuario_no_tiene_ningun_device_token_activo(): void
    {
        $notificacion = Notificacion::factory()->create(['programada_para' => now()->subMinute()]);

        $this->ejecutar();

        $this->assertDatabaseHas('notificacion', ['id' => $notificacion->id, 'estado' => 'fallida']);
    }

    public function test_envia_whatsapp_solo_si_la_plantilla_esta_aprobada(): void
    {
        $usuario = Usuario::factory()->create();
        $plantilla = PlantillaWhatsapp::factory()->create(['nombre' => 'recordatorio_final', 'estado' => 'aprobada']);
        $notificacion = Notificacion::factory()->whatsapp()->create([
            'usuario_id' => $usuario->id, 'tipo_evento' => 'recordatorio_final', 'plantilla' => $plantilla->nombre,
            'programada_para' => now()->subMinute(),
        ]);

        $this->ejecutar();

        $this->assertDatabaseHas('notificacion', ['id' => $notificacion->id, 'estado' => 'enviada']);
        $this->assertCount(1, FakeEnviadorWhatsApp::enviados());
    }

    public function test_whatsapp_sin_plantilla_aprobada_marca_fallida(): void
    {
        $notificacion = Notificacion::factory()->whatsapp()->create([
            'tipo_evento' => 'plantilla_inexistente', 'plantilla' => 'plantilla_inexistente',
            'programada_para' => now()->subMinute(),
        ]);

        $this->ejecutar();

        $this->assertDatabaseHas('notificacion', ['id' => $notificacion->id, 'estado' => 'fallida']);
        $this->assertCount(0, FakeEnviadorWhatsApp::enviados());
    }

    public function test_no_toca_notificaciones_que_todavia_no_estan_pendientes(): void
    {
        $notificacion = Notificacion::factory()->create(['programada_para' => now()->addHour()]);

        $this->ejecutar();

        $this->assertDatabaseHas('notificacion', ['id' => $notificacion->id, 'estado' => 'programada']);
    }

    private function ejecutar(): void
    {
        app(EnviarNotificacionesProgramadas::class)->handle(
            app(NotificacionService::class),
            app(EnviadorPush::class),
            app(EnviadorWhatsApp::class),
            app(EnviadorWebSocket::class),
        );
    }
}
