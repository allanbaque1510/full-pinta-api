<?php

namespace Tests\Feature\Scheduling;

use App\Models\Asignacion;
use App\Models\CatalogoServicio;
use App\Models\Cita;
use App\Models\Excepcion;
use App\Models\Habilidad;
use App\Models\HorarioLocal;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\Recurso;
use App\Models\ServicioLocal;
use App\Models\TipoRecurso;
use App\Models\Turno;
use App\Modules\Scheduling\Application\DisponibilidadService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Suite obligatoria del motor de disponibilidad (§5.7). El test de
 * concurrencia real (20 peticiones paralelas) vive aparte, en
 * `ConcurrenciaCitaTest`, porque necesita un servidor HTTP de verdad.
 */
class DisponibilidadTest extends TestCase
{
    use RefreshDatabase;

    private function servicio(): DisponibilidadService
    {
        return app(DisponibilidadService::class);
    }

    /**
     * Servicio que no necesita ningún recurso físico (`tipo_recurso = ninguno`)
     * — para los tests que no están probando el filtro 7, así no hace falta
     * crear también un `Recurso` a juego en cada uno.
     */
    private function servicioSinRecurso(Local $local, int $duracionMin, int $bufferMin = 0): ServicioLocal
    {
        $tipoNinguno = TipoRecurso::where('codigo', 'ninguno')->first()
            ?? TipoRecurso::factory()->create(['codigo' => 'ninguno', 'nombre' => 'Ninguno']);

        $catalogo = CatalogoServicio::factory()->create(['tipo_recurso_id' => $tipoNinguno->id]);

        return ServicioLocal::factory()->create([
            'local_id' => $local->id, 'catalogo_servicio_id' => $catalogo->id,
            'duracion_min' => $duracionMin, 'buffer_min' => $bufferMin,
        ]);
    }

    public function test_no_ofrece_un_slot_donde_ya_hay_una_cita(): void
    {
        $fecha = CarbonImmutable::now()->next(2); // martes futuro
        $local = Local::factory()->sinLeadTime()->create();
        HorarioLocal::factory()->create(['local_id' => $local->id, 'dia_semana' => 2, 'abre' => '09:00', 'cierra' => '19:00']);
        $profesional = Profesional::factory()->create();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        Turno::factory()->para($asignacion)->horario('09:00', '19:00', 2)->create();
        $servicioLocal = $this->servicioSinRecurso($local, 60);
        Habilidad::factory()->create(['profesional_id' => $profesional->id, 'servicio_local_id' => $servicioLocal->id]);

        Cita::factory()->create([
            'local_id' => $local->id, 'profesional_id' => $profesional->id, 'estado' => 'confirmada',
            'inicio' => $fecha->setTime(10, 0), 'fin' => $fecha->setTime(11, 0),
        ]);

        $slots = $this->servicio()->slots($local, $fecha->startOfDay(), [$servicioLocal->id]);

        $this->assertFalse($slots->contains(fn ($s) => $s->inicio->equalTo($fecha->setTime(10, 0))));
        $this->assertTrue($slots->contains(fn ($s) => $s->inicio->equalTo($fecha->setTime(9, 0))));
        $this->assertTrue($slots->contains(fn ($s) => $s->inicio->equalTo($fecha->setTime(11, 0))));
    }

    /** Kevin trabaja en dos locales (§4.6): cada uno solo lo ofrece en su propio horario. */
    public function test_un_profesional_multi_local_solo_aparece_en_el_horario_de_cada_local(): void
    {
        $fecha = CarbonImmutable::now()->next(1); // lunes futuro
        $alborada = Local::factory()->sinLeadTime()->create();
        $urdesa = Local::factory()->sinLeadTime()->create();
        HorarioLocal::factory()->create(['local_id' => $alborada->id, 'dia_semana' => 1, 'abre' => '09:00', 'cierra' => '14:00']);
        HorarioLocal::factory()->create(['local_id' => $urdesa->id, 'dia_semana' => 1, 'abre' => '18:00', 'cierra' => '23:00']);

        $kevin = Profesional::factory()->create();
        $asigAlborada = Asignacion::factory()->create(['local_id' => $alborada->id, 'profesional_id' => $kevin->id]);
        $asigUrdesa = Asignacion::factory()->create(['local_id' => $urdesa->id, 'profesional_id' => $kevin->id]);
        Turno::factory()->para($asigAlborada)->horario('09:00', '14:00', 1)->create();
        Turno::factory()->para($asigUrdesa)->horario('18:00', '23:00', 1)->create();

        $servicioAlborada = $this->servicioSinRecurso($alborada, 30);
        $servicioUrdesa = $this->servicioSinRecurso($urdesa, 30);
        Habilidad::factory()->create(['profesional_id' => $kevin->id, 'servicio_local_id' => $servicioAlborada->id]);
        Habilidad::factory()->create(['profesional_id' => $kevin->id, 'servicio_local_id' => $servicioUrdesa->id]);

        $slotsAlborada = $this->servicio()->slots($alborada, $fecha->startOfDay(), [$servicioAlborada->id]);
        $slotsUrdesa = $this->servicio()->slots($urdesa, $fecha->startOfDay(), [$servicioUrdesa->id]);

        $this->assertTrue($slotsAlborada->isNotEmpty());
        $this->assertTrue($slotsUrdesa->isNotEmpty());
        $this->assertTrue($slotsAlborada->every(fn ($s) => $s->inicio->hour >= 9 && $s->inicio->hour < 14));
        $this->assertTrue($slotsUrdesa->every(fn ($s) => $s->inicio->hour >= 18 && $s->inicio->hour < 23));
    }

    public function test_respeta_el_traslado_minimo_entre_citas_de_locales_distintos(): void
    {
        $fecha = CarbonImmutable::now()->next(3); // miércoles futuro
        $localA = Local::factory()->sinLeadTime()->create();
        $localB = Local::factory()->sinLeadTime()->create();
        // El profesional no puede trabajar horas traslapadas en dos locales
        // (constraint `turno_sin_traslape`, §4.11) — turnos consecutivos, no simultáneos.
        HorarioLocal::factory()->create(['local_id' => $localA->id, 'dia_semana' => 3, 'abre' => '09:00', 'cierra' => '13:00']);
        HorarioLocal::factory()->create(['local_id' => $localB->id, 'dia_semana' => 3, 'abre' => '13:00', 'cierra' => '20:00']);

        $profesional = Profesional::factory()->create(['traslado_min' => 60]);
        $asigA = Asignacion::factory()->create(['local_id' => $localA->id, 'profesional_id' => $profesional->id]);
        $asigB = Asignacion::factory()->create(['local_id' => $localB->id, 'profesional_id' => $profesional->id]);
        Turno::factory()->para($asigA)->horario('09:00', '13:00', 3)->create();
        Turno::factory()->para($asigB)->horario('13:00', '20:00', 3)->create();

        $servicioB = $this->servicioSinRecurso($localB, 30);
        Habilidad::factory()->create(['profesional_id' => $profesional->id, 'servicio_local_id' => $servicioB->id]);

        // Última cita de la mañana en el local A, termina justo cuando A cierra.
        Cita::factory()->create([
            'local_id' => $localA->id, 'profesional_id' => $profesional->id, 'estado' => 'confirmada',
            'inicio' => $fecha->setTime(12, 0), 'fin' => $fecha->setTime(13, 0),
        ]);

        $slotsB = $this->servicio()->slots($localB, $fecha->startOfDay(), [$servicioB->id]);

        // Con traslado_min=60, nada entre 13:00 y 14:00 en el OTRO local.
        $this->assertFalse($slotsB->contains(
            fn ($s) => $s->inicio->gte($fecha->setTime(13, 0)) && $s->inicio->lt($fecha->setTime(14, 0)),
        ));
        $this->assertTrue($slotsB->contains(fn ($s) => $s->inicio->equalTo($fecha->setTime(14, 0))));
    }

    /** Un local puede tener 4 barberos pero 1 sola mesa de uñas (§4.6). */
    public function test_un_recurso_compartido_limita_aunque_haya_otro_profesional_libre(): void
    {
        $fecha = CarbonImmutable::now()->next(4); // jueves futuro
        $local = Local::factory()->sinLeadTime()->create();
        HorarioLocal::factory()->create(['local_id' => $local->id, 'dia_semana' => 4, 'abre' => '09:00', 'cierra' => '19:00']);
        $mesa = Recurso::factory()->tipo('mesa_unas', 'Mesa 1')->create(['local_id' => $local->id]);

        $ana = Profesional::factory()->create();
        $beti = Profesional::factory()->create();
        foreach ([$ana, $beti] as $manicurista) {
            $asignacion = Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $manicurista->id]);
            Turno::factory()->para($asignacion)->horario('09:00', '19:00', 4)->create();
        }

        $catalogo = CatalogoServicio::factory()->create(['tipo_recurso_id' => $mesa->tipo_recurso_id]);
        $servicioLocal = ServicioLocal::factory()->create([
            'local_id' => $local->id, 'catalogo_servicio_id' => $catalogo->id, 'duracion_min' => 60, 'buffer_min' => 0,
        ]);
        Habilidad::factory()->create(['profesional_id' => $ana->id, 'servicio_local_id' => $servicioLocal->id]);
        Habilidad::factory()->create(['profesional_id' => $beti->id, 'servicio_local_id' => $servicioLocal->id]);

        // Ana ya tiene LA MESA reservada 10-11 (da igual con cuál profesional).
        Cita::factory()->create([
            'local_id' => $local->id, 'profesional_id' => $ana->id, 'recurso_id' => $mesa->id, 'estado' => 'confirmada',
            'inicio' => $fecha->setTime(10, 0), 'fin' => $fecha->setTime(11, 0),
        ]);

        $slots = $this->servicio()->slots($local, $fecha->startOfDay(), [$servicioLocal->id]);

        // A las 10:00 nadie agenda: la única mesa está ocupada, aunque Beti esté libre.
        $this->assertFalse($slots->contains(fn ($s) => $s->inicio->equalTo($fecha->setTime(10, 0))));
        $this->assertTrue($slots->contains(fn ($s) => $s->inicio->equalTo($fecha->setTime(11, 0))));
    }

    public function test_una_excepcion_de_local_bloquea_a_todos_ahi(): void
    {
        $fecha = CarbonImmutable::now()->next(5); // viernes futuro
        $local = Local::factory()->sinLeadTime()->create();
        HorarioLocal::factory()->create(['local_id' => $local->id, 'dia_semana' => 5, 'abre' => '09:00', 'cierra' => '19:00']);
        $profesional = Profesional::factory()->create();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        Turno::factory()->para($asignacion)->horario('09:00', '19:00', 5)->create();
        $servicioLocal = $this->servicioSinRecurso($local, 30);
        Habilidad::factory()->create(['profesional_id' => $profesional->id, 'servicio_local_id' => $servicioLocal->id]);

        Excepcion::factory()->create([
            'local_id' => $local->id, 'motivo' => 'feriado',
            'fecha_inicio' => $fecha->startOfDay(), 'fecha_fin' => $fecha->endOfDay(),
        ]);

        $slots = $this->servicio()->slots($local, $fecha->startOfDay(), [$servicioLocal->id]);

        $this->assertTrue($slots->isEmpty());
    }

    /** Una ausencia de PROFESIONAL bloquea TODOS sus locales, no solo uno (§4.6). */
    public function test_una_excepcion_de_profesional_lo_bloquea_en_todos_sus_locales(): void
    {
        $fecha = CarbonImmutable::now()->next(6); // sábado futuro
        $localA = Local::factory()->sinLeadTime()->create();
        $localB = Local::factory()->sinLeadTime()->create();
        // Turnos consecutivos, no simultáneos: un profesional no puede
        // trabajar horas traslapadas en dos locales (§4.11).
        HorarioLocal::factory()->create(['local_id' => $localA->id, 'dia_semana' => 6, 'abre' => '09:00', 'cierra' => '13:00']);
        HorarioLocal::factory()->create(['local_id' => $localB->id, 'dia_semana' => 6, 'abre' => '15:00', 'cierra' => '19:00']);

        $profesional = Profesional::factory()->create();
        $asigA = Asignacion::factory()->create(['local_id' => $localA->id, 'profesional_id' => $profesional->id]);
        $asigB = Asignacion::factory()->create(['local_id' => $localB->id, 'profesional_id' => $profesional->id]);
        Turno::factory()->para($asigA)->horario('09:00', '13:00', 6)->create();
        Turno::factory()->para($asigB)->horario('15:00', '19:00', 6)->create();

        $servicioA = $this->servicioSinRecurso($localA, 30);
        $servicioB = $this->servicioSinRecurso($localB, 30);
        Habilidad::factory()->create(['profesional_id' => $profesional->id, 'servicio_local_id' => $servicioA->id]);
        Habilidad::factory()->create(['profesional_id' => $profesional->id, 'servicio_local_id' => $servicioB->id]);

        Excepcion::factory()->deProfesional($profesional->id)->create([
            'fecha_inicio' => $fecha->startOfDay(), 'fecha_fin' => $fecha->endOfDay(),
        ]);

        $this->assertTrue($this->servicio()->slots($localA, $fecha->startOfDay(), [$servicioA->id])->isEmpty());
        $this->assertTrue($this->servicio()->slots($localB, $fecha->startOfDay(), [$servicioB->id])->isEmpty());
    }

    public function test_una_excepcion_de_recurso_bloquea_solo_ese_recurso(): void
    {
        $fecha = CarbonImmutable::now()->next(0); // domingo futuro
        $local = Local::factory()->sinLeadTime()->create();
        HorarioLocal::factory()->create(['local_id' => $local->id, 'dia_semana' => 0, 'abre' => '09:00', 'cierra' => '19:00']);
        $mesa = Recurso::factory()->tipo('mesa_unas', 'Mesa 1')->create(['local_id' => $local->id]);

        $profesional = Profesional::factory()->create();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        Turno::factory()->para($asignacion)->horario('09:00', '19:00', 0)->create();

        $catalogo = CatalogoServicio::factory()->create(['tipo_recurso_id' => $mesa->tipo_recurso_id]);
        $servicioLocal = ServicioLocal::factory()->create([
            'local_id' => $local->id, 'catalogo_servicio_id' => $catalogo->id, 'duracion_min' => 60, 'buffer_min' => 0,
        ]);
        Habilidad::factory()->create(['profesional_id' => $profesional->id, 'servicio_local_id' => $servicioLocal->id]);

        // Mantenimiento de la (única) mesa, toda la mañana.
        Excepcion::factory()->deRecurso($mesa->id)->create([
            'fecha_inicio' => $fecha->setTime(9, 0), 'fecha_fin' => $fecha->setTime(12, 0),
        ]);

        $slots = $this->servicio()->slots($local, $fecha->startOfDay(), [$servicioLocal->id]);

        $this->assertFalse($slots->contains(fn ($s) => $s->inicio->equalTo($fecha->setTime(10, 0))));
        $this->assertTrue($slots->contains(fn ($s) => $s->inicio->equalTo($fecha->setTime(12, 0))));
    }

    /** Turno partido por cruzar medianoche (§4.6): dos filas, dos días distintos. */
    public function test_un_turno_partido_en_medianoche_ofrece_slots_en_ambos_dias(): void
    {
        $miercoles = CarbonImmutable::now()->next(3);
        $jueves = $miercoles->addDay();

        $local = Local::factory()->sinLeadTime()->create();
        HorarioLocal::factory()->create(['local_id' => $local->id, 'dia_semana' => 3, 'abre' => '18:00', 'cierra' => '23:59']);
        HorarioLocal::factory()->create(['local_id' => $local->id, 'dia_semana' => 4, 'abre' => '00:00', 'cierra' => '02:00']);

        $profesional = Profesional::factory()->create();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        Turno::factory()->para($asignacion)->horario('20:00', '23:59', 3)->create();
        Turno::factory()->para($asignacion)->horario('00:00', '01:00', 4)->create();

        $servicioLocal = $this->servicioSinRecurso($local, 30);
        Habilidad::factory()->create(['profesional_id' => $profesional->id, 'servicio_local_id' => $servicioLocal->id]);

        $slotsMiercoles = $this->servicio()->slots($local, $miercoles->startOfDay(), [$servicioLocal->id]);
        $slotsJueves = $this->servicio()->slots($local, $jueves->startOfDay(), [$servicioLocal->id]);

        $this->assertTrue($slotsMiercoles->contains(fn ($s) => $s->inicio->equalTo($miercoles->setTime(22, 0))));
        $this->assertTrue($slotsJueves->contains(fn ($s) => $s->inicio->equalTo($jueves->setTime(0, 0))));
    }

    /** Si el worker de expiración de holds se cayó, un hold vencido no debe bloquear el slot (§5.4). */
    public function test_un_hold_vencido_no_bloquea_pero_uno_vigente_si(): void
    {
        $fecha = CarbonImmutable::now()->next(2);
        $local = Local::factory()->sinLeadTime()->create();
        HorarioLocal::factory()->create(['local_id' => $local->id, 'dia_semana' => 2, 'abre' => '09:00', 'cierra' => '19:00']);
        $profesional = Profesional::factory()->create();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        Turno::factory()->para($asignacion)->horario('09:00', '19:00', 2)->create();
        $servicioLocal = $this->servicioSinRecurso($local, 30);
        Habilidad::factory()->create(['profesional_id' => $profesional->id, 'servicio_local_id' => $servicioLocal->id]);

        Cita::factory()->holdVencido()->create([
            'local_id' => $local->id, 'profesional_id' => $profesional->id,
            'inicio' => $fecha->setTime(10, 0), 'fin' => $fecha->setTime(10, 30),
        ]);
        Cita::factory()->reservada()->create([
            'local_id' => $local->id, 'profesional_id' => $profesional->id,
            'inicio' => $fecha->setTime(12, 0), 'fin' => $fecha->setTime(12, 30),
        ]);

        $slots = $this->servicio()->slots($local, $fecha->startOfDay(), [$servicioLocal->id]);

        $this->assertTrue($slots->contains(fn ($s) => $s->inicio->equalTo($fecha->setTime(10, 0))));
        $this->assertFalse($slots->contains(fn ($s) => $s->inicio->equalTo($fecha->setTime(12, 0))));
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }
}
