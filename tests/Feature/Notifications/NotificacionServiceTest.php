<?php

namespace Tests\Feature\Notifications;

use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\NotificacionCategoria;
use App\Models\PreferenciaNotificacion;
use App\Models\Profesional;
use App\Models\ServicioLocal;
use App\Models\Usuario;
use App\Modules\Scheduling\Application\Transiciones\CancelarCita;
use App\Modules\Scheduling\Application\Transiciones\ReagendarCita;
use App\Modules\Scheduling\Events\CitaCreada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los listeners de dominio (§11.2) crean filas `notificacion` reales al
 * reaccionar a los eventos de Scheduling — se prueba disparando el flujo
 * completo (crear/cancelar/reagendar una cita), no invocando el listener a mano.
 */
class NotificacionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_una_cita_programa_notificacion_al_cliente_y_al_profesional_con_cuenta(): void
    {
        $cliente = Usuario::factory()->create();
        $profesional = Profesional::factory()->conCuenta()->create();
        $cita = Cita::factory()->create(['cliente_id' => $cliente->id, 'profesional_id' => $profesional->id]);

        CitaCreada::dispatch($cita->id);

        $this->assertDatabaseHas('notificacion', [
            'usuario_id' => $cliente->id, 'cita_id' => $cita->id,
            'tipo_evento' => 'cita_creada_cliente', 'canal' => 'push',
        ]);
        $this->assertDatabaseHas('notificacion', [
            'usuario_id' => $cliente->id, 'cita_id' => $cita->id,
            'tipo_evento' => 'cita_creada_cliente', 'canal' => 'whatsapp',
        ]);
        $this->assertDatabaseHas('notificacion', [
            'usuario_id' => $profesional->usuario_id, 'cita_id' => $cita->id,
            'tipo_evento' => 'cita_creada_profesional', 'canal' => 'push',
        ]);
    }

    public function test_no_programa_nada_para_el_profesional_sin_cuenta_propia(): void
    {
        $profesional = Profesional::factory()->create(); // sin usuario_id
        $cita = Cita::factory()->create(['profesional_id' => $profesional->id]);

        CitaCreada::dispatch($cita->id);

        $this->assertDatabaseMissing('notificacion', ['tipo_evento' => 'cita_creada_profesional']);
    }

    public function test_una_preferencia_apagada_bloquea_el_canal_no_obligatorio(): void
    {
        $cliente = Usuario::factory()->create();
        $profesional = Profesional::factory()->conCuenta()->create();
        $categoriaAgenda = NotificacionCategoria::firstOrCreate(['codigo' => 'agenda'], ['nombre' => 'Agenda', 'activo' => true]);

        PreferenciaNotificacion::create([
            'usuario_id' => $profesional->usuario_id, 'categoria_id' => $categoriaAgenda->id, 'push' => false, 'whatsapp' => false,
        ]);

        $cita = Cita::factory()->create(['cliente_id' => $cliente->id, 'profesional_id' => $profesional->id]);
        CitaCreada::dispatch($cita->id);

        // No obligatorio (agenda) respeta la preferencia apagada — el push
        // del profesional no se programa. `websocket` no tiene preferencia
        // modelada (§4.9), así que esa fila sí existe; no es lo que se prueba aquí.
        $this->assertDatabaseMissing('notificacion', ['usuario_id' => $profesional->usuario_id, 'canal' => 'push']);
        $this->assertDatabaseHas('notificacion', ['usuario_id' => $profesional->usuario_id, 'canal' => 'websocket']);
        // Obligatorio (citas, cliente) se manda igual.
        $this->assertDatabaseHas('notificacion', ['usuario_id' => $cliente->id, 'tipo_evento' => 'cita_creada_cliente']);
    }

    public function test_programar_dos_veces_el_mismo_evento_no_duplica(): void
    {
        $cita = Cita::factory()->create();

        CitaCreada::dispatch($cita->id);
        CitaCreada::dispatch($cita->id); // reintento de la cola, por ejemplo

        $this->assertEquals(
            1,
            Notificacion::where('cita_id', $cita->id)
                ->where('tipo_evento', 'cita_creada_cliente')->where('canal', 'push')->count(),
        );
    }

    public function test_cancelar_por_el_local_notifica_al_cliente_y_cancelar_por_el_cliente_notifica_al_profesional(): void
    {
        $staffDelLocal = Usuario::factory()->create(); // cualquier actor distinto del cliente => cancela_local
        $citaCancelaLocal = Cita::factory()->create();

        app(CancelarCita::class)($citaCancelaLocal, $staffDelLocal, null);

        $this->assertDatabaseHas('notificacion', [
            'usuario_id' => $citaCancelaLocal->cliente_id, 'tipo_evento' => 'cita_cancelada_cliente',
        ]);
    }

    public function test_reagendar_cancela_las_notificaciones_pendientes_de_la_cita_vieja(): void
    {
        $cliente = Usuario::factory()->create();
        $citaVieja = Cita::factory()->reservada()->create(['cliente_id' => $cliente->id]);
        $servicio = ServicioLocal::factory()->create(['local_id' => $citaVieja->local_id]);

        CitaCreada::dispatch($citaVieja->id); // programa el recordatorio/notificación original

        $this->assertDatabaseHas('notificacion', ['cita_id' => $citaVieja->id, 'estado' => 'programada']);

        app(ReagendarCita::class)($citaVieja, $cliente, [
            'profesional_id' => $citaVieja->profesional_id,
            'servicios' => [$servicio->id],
            'inicio' => now()->addDays(3)->toIso8601String(),
        ]);

        $this->assertDatabaseHas('notificacion', ['cita_id' => $citaVieja->id, 'estado' => 'cancelada']);
    }
}
