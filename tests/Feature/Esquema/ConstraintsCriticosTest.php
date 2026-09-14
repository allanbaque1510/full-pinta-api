<?php

namespace Tests\Feature\Esquema;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Los tres constraints críticos del §4.11.
 *
 * Son lo que hace correcto el sistema: sin ellos la lógica de aplicación falla
 * bajo concurrencia. Estas pruebas van contra Postgres de verdad, no contra
 * mocks — un EXCLUDE USING gist no se puede simular.
 */
class ConstraintsCriticosTest extends TestCase
{
    use RefreshDatabase;

    private const EXCLUSION_VIOLATION = '23P01';

    private string $profesional;

    private string $localA;

    private string $localB;

    private string $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cliente = $this->crearUsuario('0990000001', 'Cliente');
        $duenoA = $this->crearUsuario('0990000002', 'Dueño A');
        $duenoB = $this->crearUsuario('0990000003', 'Dueño B');

        $this->localA = $this->crearLocal($duenoA, 'Alborada');
        $this->localB = $this->crearLocal($duenoB, 'Urdesa');
        $this->profesional = $this->crearProfesional('Kevin');
    }

    public function test_un_profesional_no_puede_tener_dos_turnos_traslapados(): void
    {
        $this->crearTurno($this->localA, '09:00', '14:00');

        $this->expectExclusionViolation();
        $this->crearTurno($this->localA, '13:00', '15:00');
    }

    /**
     * El caso que motiva el constraint: el traslape se detecta aunque los
     * turnos sean de locales distintos. Sin esto, un local crea un turno que
     * pisa el de otro y el conflicto aparece al agendar, con el cliente
     * esperando.
     */
    public function test_el_traslape_de_turnos_se_detecta_entre_locales_distintos(): void
    {
        $this->crearTurno($this->localA, '09:00', '14:00');

        $this->expectExclusionViolation();
        $this->crearTurno($this->localB, '13:00', '15:00');
    }

    /**
     * Los rangos son '[)': un turno que termina 14:00 y otro que empieza 14:00
     * no se traslapan. Con '[]' se perdería un slot en cada frontera.
     */
    public function test_dos_turnos_que_se_tocan_en_la_frontera_son_validos(): void
    {
        $this->crearTurno($this->localA, '09:00', '14:00');
        $this->crearTurno($this->localB, '14:00', '18:00');

        $this->assertSame(2, DB::table('turno')->count());
    }

    /**
     * `vigencia` en el constraint es lo que permite cambiar el horario a futuro
     * sin que Postgres rechace el turno nuevo por chocar con el actual.
     */
    public function test_el_mismo_turno_con_vigencia_distinta_es_valido(): void
    {
        $this->crearTurno($this->localA, '09:00', '14:00', '2026-01-01', '2026-06-30');
        $this->crearTurno($this->localA, '09:00', '14:00', '2026-07-01', null);

        $this->assertSame(2, DB::table('turno')->count());
    }

    /**
     * El constraint es sobre `profesional_id` solo, NO sobre
     * (local_id, profesional_id): un profesional es un solo cuerpo y no puede
     * estar en dos locales a la vez.
     */
    public function test_un_profesional_no_puede_tener_dos_citas_traslapadas_ni_en_locales_distintos(): void
    {
        $this->crearCita($this->localA, '2026-03-02 14:00:00', '2026-03-02 15:00:00');

        $this->expectExclusionViolation();
        $this->crearCita($this->localB, '2026-03-02 14:30:00', '2026-03-02 15:30:00');
    }

    public function test_una_cita_cancelada_libera_el_slot(): void
    {
        $this->crearCita($this->localA, '2026-03-02 14:00:00', '2026-03-02 15:00:00', 'cancelada_cliente');

        // El constraint solo aplica a reservada/confirmada/en_curso, así que el
        // horario vuelve a estar disponible.
        $this->crearCita($this->localA, '2026-03-02 14:00:00', '2026-03-02 15:00:00');

        $this->assertSame(2, DB::table('cita')->count());
    }

    public function test_un_recurso_no_puede_estar_ocupado_dos_veces(): void
    {
        $mesa = $this->crearRecurso($this->localA, 'mesa_unas', 'Mesa 1');
        $otroProfesional = $this->crearProfesional('Ana');

        $this->crearCita($this->localA, '2026-03-02 14:00:00', '2026-03-02 15:00:00', recursoId: $mesa);

        // Dos manicuristas libres, pero una sola mesa: la segunda cita no cabe.
        $this->expectExclusionViolation();
        $this->crearCita(
            $this->localA,
            '2026-03-02 14:30:00',
            '2026-03-02 15:30:00',
            profesionalId: $otroProfesional,
            recursoId: $mesa,
        );
    }

    public function test_dos_citas_simultaneas_usan_recursos_distintos_sin_conflicto(): void
    {
        $mesa1 = $this->crearRecurso($this->localA, 'mesa_unas', 'Mesa 1');
        $mesa2 = $this->crearRecurso($this->localA, 'mesa_unas', 'Mesa 2');
        $otroProfesional = $this->crearProfesional('Ana');

        $this->crearCita($this->localA, '2026-03-02 14:00:00', '2026-03-02 15:00:00', recursoId: $mesa1);
        $this->crearCita(
            $this->localA,
            '2026-03-02 14:00:00',
            '2026-03-02 15:00:00',
            profesionalId: $otroProfesional,
            recursoId: $mesa2,
        );

        $this->assertSame(2, DB::table('cita')->count());
    }

    // --- Ayudantes -------------------------------------------------------

    private function expectExclusionViolation(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode(self::EXCLUSION_VIOLATION);
    }

    private function crearUsuario(string $telefono, string $nombre): string
    {
        $id = (string) Str::uuid();

        DB::table('usuario')->insert([
            'id' => $id,
            'telefono' => $telefono,
            'nombre' => $nombre,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function crearLocal(string $propietarioId, string $nombre): string
    {
        $negocioId = (string) Str::uuid();

        DB::table('negocio')->insert([
            'id' => $negocioId,
            'nombre_marca' => $nombre,
            'propietario_id' => $propietarioId,
            'plan_id' => $this->parametroId('plan', 'free'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $localId = (string) Str::uuid();

        DB::table('local')->insert([
            'id' => $localId,
            'negocio_id' => $negocioId,
            'nombre' => $nombre,
            'direccion' => 'Guayaquil',
            'ubicacion' => DB::raw("ST_GeogFromText('SRID=4326;POINT(-79.8891 -2.1894)')"),
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $localId;
    }

    private function crearProfesional(string $nombre): string
    {
        $id = (string) Str::uuid();

        DB::table('profesional')->insert([
            'id' => $id,
            'nombre' => $nombre,
            'independiente' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function crearRecurso(string $localId, string $tipo, string $nombre): string
    {
        $id = (string) Str::uuid();

        DB::table('recurso')->insert([
            'id' => $id,
            'local_id' => $localId,
            'tipo_recurso_id' => $this->parametroId('tipo_recurso', $tipo),
            'nombre' => $nombre,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * Busca (o crea) la fila de una tabla de parámetros por `codigo` y
     * devuelve su id — memoizado, porque varios helpers de este test piden el
     * mismo código repetidas veces.
     */
    private function parametroId(string $tabla, string $codigo): string
    {
        $existente = DB::table($tabla)->where('codigo', $codigo)->value('id');

        if ($existente !== null) {
            return $existente;
        }

        $id = (string) Str::uuid();

        DB::table($tabla)->insert(['id' => $id, 'codigo' => $codigo, 'nombre' => $codigo, 'activo' => true]);

        return $id;
    }

    private function crearTurno(
        string $localId,
        string $entra,
        string $sale,
        string $vigenteDesde = '2026-01-01',
        ?string $vigenteHasta = null,
    ): void {
        $asignacionId = (string) Str::uuid();

        DB::table('asignacion')->insert([
            'id' => $asignacionId,
            'local_id' => $localId,
            'profesional_id' => $this->profesional,
            'rol' => 'barbero',
            'modalidad' => 'empleado',
            'comision_pct' => 50,
            'desde' => $vigenteDesde,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('turno')->insert([
            'id' => (string) Str::uuid(),
            'asignacion_id' => $asignacionId,
            'profesional_id' => $this->profesional,
            'local_id' => $localId,
            'dia_semana' => 1,
            'entra' => $entra,
            'sale' => $sale,
            'vigente_desde' => $vigenteDesde,
            'vigente_hasta' => $vigenteHasta,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearCita(
        string $localId,
        string $inicio,
        string $fin,
        string $estado = 'confirmada',
        ?string $profesionalId = null,
        ?string $recursoId = null,
    ): void {
        DB::table('cita')->insert([
            'id' => (string) Str::uuid(),
            'local_id' => $localId,
            'profesional_id' => $profesionalId ?? $this->profesional,
            'recurso_id' => $recursoId,
            'cliente_id' => $this->cliente,
            'inicio' => $inicio,
            'fin' => $fin,
            'estado' => $estado,
            'canal' => 'app',
            'codigo' => strtoupper(Str::random(8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
