<?php

namespace Tests\Feature\Notifications;

use App\Models\Cita;
use App\Models\Resena;
use App\Modules\Notifications\Application\NotificacionService;
use App\Modules\Notifications\Jobs\ProgramarSolicitudesResena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** §11.2: "pedir reseña" → cliente, push, 2h después de completada. */
class ProgramarSolicitudesResenaTest extends TestCase
{
    use RefreshDatabase;

    public function test_programa_la_solicitud_de_resena_de_una_cita_completada_reciente(): void
    {
        $cita = Cita::factory()->completada()->create(['completada_at' => now()->subHours(3)]);

        app(ProgramarSolicitudesResena::class)->handle(app(NotificacionService::class));

        $this->assertDatabaseHas('notificacion', [
            'cita_id' => $cita->id, 'tipo_evento' => 'pedir_resena', 'canal' => 'push',
        ]);
    }

    public function test_no_programa_nada_si_la_cita_ya_tiene_resena(): void
    {
        $cita = Cita::factory()->completada()->create(['completada_at' => now()->subHours(3)]);
        Resena::factory()->create(['cita_id' => $cita->id]);

        app(ProgramarSolicitudesResena::class)->handle(app(NotificacionService::class));

        $this->assertDatabaseMissing('notificacion', ['cita_id' => $cita->id, 'tipo_evento' => 'pedir_resena']);
    }

    public function test_no_programa_nada_para_una_cita_completada_hace_mas_de_14_dias(): void
    {
        $cita = Cita::factory()->completada()->create(['completada_at' => now()->subDays(20)]);

        app(ProgramarSolicitudesResena::class)->handle(app(NotificacionService::class));

        $this->assertDatabaseMissing('notificacion', ['cita_id' => $cita->id]);
    }
}
