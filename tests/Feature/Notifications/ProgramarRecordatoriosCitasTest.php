<?php

namespace Tests\Feature\Notifications;

use App\Models\Cita;
use App\Models\Notificacion;
use App\Modules\Notifications\Application\NotificacionService;
use App\Modules\Notifications\Jobs\ProgramarRecordatoriosCitas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** §11.2: recordatorio 24h y recordatorio final (2-3h antes). */
class ProgramarRecordatoriosCitasTest extends TestCase
{
    use RefreshDatabase;

    public function test_programa_los_dos_recordatorios_de_una_cita_confirmada_futura(): void
    {
        $cita = Cita::factory()->entre(now()->addDays(2), now()->addDays(2)->addHour())->create(['estado' => 'confirmada']);

        app(ProgramarRecordatoriosCitas::class)->handle(app(NotificacionService::class));

        $this->assertDatabaseHas('notificacion', [
            'cita_id' => $cita->id, 'tipo_evento' => 'recordatorio_24h', 'canal' => 'push',
        ]);
        $this->assertDatabaseHas('notificacion', [
            'cita_id' => $cita->id, 'tipo_evento' => 'recordatorio_final', 'canal' => 'whatsapp',
        ]);
    }

    public function test_no_programa_nada_para_una_cita_que_no_esta_confirmada(): void
    {
        $cita = Cita::factory()->entre(now()->addDays(2), now()->addDays(2)->addHour())->reservada()->create();

        app(ProgramarRecordatoriosCitas::class)->handle(app(NotificacionService::class));

        $this->assertDatabaseMissing('notificacion', ['cita_id' => $cita->id]);
    }

    public function test_correr_el_job_dos_veces_no_duplica_los_recordatorios(): void
    {
        $servicio = app(NotificacionService::class);
        $cita = Cita::factory()->entre(now()->addDays(2), now()->addDays(2)->addHour())->create(['estado' => 'confirmada']);

        app(ProgramarRecordatoriosCitas::class)->handle($servicio);
        app(ProgramarRecordatoriosCitas::class)->handle($servicio);

        $this->assertEquals(
            1,
            Notificacion::where('cita_id', $cita->id)->where('tipo_evento', 'recordatorio_24h')->count(),
        );
    }
}
