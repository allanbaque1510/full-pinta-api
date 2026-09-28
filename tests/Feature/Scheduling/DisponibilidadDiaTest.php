<?php

namespace Tests\Feature\Scheduling;

use App\Models\Asignacion;
use App\Models\DisponibilidadDia;
use App\Models\Habilidad;
use App\Models\HorarioLocal;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\ServicioLocal;
use App\Models\TipoRecurso;
use App\Models\Turno;
use App\Models\Usuario;
use App\Modules\Scheduling\Application\CitaService;
use App\Modules\Scheduling\Application\Transiciones\CancelarCita;
use App\Modules\Scheduling\Jobs\ReconstruirDisponibilidadDia;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Revisión de base de datos 2026-09-28: `disponibilidad_dia` (la proyección
 * que consulta la búsqueda para "disponible hoy/mañana") no se recalculaba
 * al crear ni cancelar una cita — solo reaccionaba a cambios de
 * `turno`/`excepcion`. Un local podía quedar sin cupos reales y la búsqueda
 * lo seguía mostrando disponible hasta que algo más disparara la
 * reconstrucción.
 */
class DisponibilidadDiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_y_cancelar_una_cita_recalcula_disponibilidad_dia(): void
    {
        $fecha = CarbonImmutable::now()->next(2); // martes
        $local = Local::factory()->sinLeadTime()->create();
        HorarioLocal::factory()->create(['local_id' => $local->id, 'dia_semana' => 2, 'abre' => '09:00', 'cierra' => '10:00']);

        $profesional = Profesional::factory()->create();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        Turno::factory()->para($asignacion)->horario('09:00', '10:00', 2)->create();

        $tipoNinguno = TipoRecurso::where('codigo', 'ninguno')->first()
            ?? TipoRecurso::factory()->create(['codigo' => 'ninguno', 'nombre' => 'Ninguno']);
        $servicioLocal = ServicioLocal::factory()->create([
            'local_id' => $local->id, 'duracion_min' => 30, 'buffer_min' => 0,
        ]);
        $servicioLocal->catalogoServicio()->update(['tipo_recurso_id' => $tipoNinguno->id]);
        Habilidad::factory()->create(['profesional_id' => $profesional->id, 'servicio_local_id' => $servicioLocal->id]);

        $cliente = Usuario::factory()->create();

        // Línea base: sin ninguna cita todavía.
        ReconstruirDisponibilidadDia::dispatchSync($local, $fecha);
        $slotsAntes = DisponibilidadDia::where('local_id', $local->id)->where('fecha', $fecha->toDateString())->value('slots_libres');
        $this->assertGreaterThan(0, $slotsAntes);

        $cita = app(CitaService::class)->reservar($local, $cliente, [
            'profesional_id' => $profesional->id,
            'servicios' => [$servicioLocal->id],
            'inicio' => $fecha->setTime(9, 0)->toIso8601String(),
        ]);

        $slotsTrasCrear = DisponibilidadDia::where('local_id', $local->id)->where('fecha', $fecha->toDateString())->value('slots_libres');
        $this->assertLessThan($slotsAntes, $slotsTrasCrear, 'CitaCreada debió disparar la reconstrucción de disponibilidad_dia.');

        app(CancelarCita::class)($cita, $cliente);

        $slotsTrasCancelar = DisponibilidadDia::where('local_id', $local->id)->where('fecha', $fecha->toDateString())->value('slots_libres');
        $this->assertSame($slotsAntes, $slotsTrasCancelar, 'CitaCancelada debió disparar la reconstrucción de disponibilidad_dia.');
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }
}
