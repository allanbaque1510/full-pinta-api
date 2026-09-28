<?php

namespace Tests\Feature\Billing;

use App\Models\Asignacion;
use App\Models\Cobro;
use App\Models\Local;
use App\Models\Negocio;
use App\Models\Plan;
use App\Models\Profesional;
use App\Models\Suscripcion;
use App\Modules\Billing\Application\CobroService;
use App\Modules\Billing\Application\SuscripcionService;
use App\Modules\Billing\Jobs\ActualizarVigenciaSuscripciones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ciclo de vigencia de la suscripción (§9.6, §10.1): renovación, cancelación
 * diferida y vencimiento tras el margen de gracia (10 días, `config/fullpinta.php`).
 */
class ActualizarVigenciaSuscripcionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::factory()->pro()->create(['codigo' => 'pro']);
        Plan::factory()->create(['codigo' => 'free']);
    }

    public function test_una_suscripcion_activa_que_se_cumplio_registra_un_cobro_y_pasa_a_gracia(): void
    {
        $negocio = Negocio::factory()->pro()->create();
        $suscripcion = Suscripcion::factory()->create([
            'negocio_id' => $negocio->id, 'estado' => 'activa', 'vigente_hasta' => now()->subDay()->toDateString(),
        ]);

        app(ActualizarVigenciaSuscripciones::class)->handle(app(CobroService::class), app(SuscripcionService::class));

        $this->assertSame('gracia', $suscripcion->fresh()->estado);
        $this->assertSame(1, Cobro::where('suscripcion_id', $suscripcion->id)->where('estado', 'pendiente')->count());
        // Sigue en Pro: el cobro está pendiente, no vencido todavía.
        $this->assertTrue($negocio->fresh()->esPro());
    }

    /**
     * Revisión de base de datos 2026-09-28: el precio se recalcula con el
     * conteo REAL de profesionales (asignaciones vigentes), no con lo que
     * el negocio pidió al activar — un profesional que trabaja en dos
     * locales del mismo negocio no cuenta dos veces, y uno cuya asignación
     * ya terminó no cuenta.
     */
    public function test_al_renovar_recalcula_profesionales_y_precio_segun_las_asignaciones_reales(): void
    {
        $negocio = Negocio::factory()->pro()->create();
        $localA = Local::factory()->create(['negocio_id' => $negocio->id]);
        $localB = Local::factory()->create(['negocio_id' => $negocio->id]);

        $compartido = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $localA->id, 'profesional_id' => $compartido->id]);
        Asignacion::factory()->create(['local_id' => $localB->id, 'profesional_id' => $compartido->id]);

        $soloEnB = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $localB->id, 'profesional_id' => $soloEnB->id]);

        $terminada = Profesional::factory()->create();
        Asignacion::factory()->create([
            'local_id' => $localA->id, 'profesional_id' => $terminada->id, 'hasta' => now()->subDay()->toDateString(),
        ]);

        $suscripcion = Suscripcion::factory()->create([
            'negocio_id' => $negocio->id, 'estado' => 'activa', 'profesionales' => 1,
            'vigente_hasta' => now()->subDay()->toDateString(),
        ]);

        app(ActualizarVigenciaSuscripciones::class)->handle(app(CobroService::class), app(SuscripcionService::class));

        $suscripcion->refresh();
        // 2 profesionales reales: $compartido (una vez, no dos) y $soloEnB. $terminada no cuenta.
        $this->assertSame(2, $suscripcion->profesionales);
        $this->assertSame('13.00', $suscripcion->precio_mensual); // 8 + 5*(2-1), plan pro
    }

    public function test_una_suscripcion_en_gracia_dentro_del_margen_no_cambia(): void
    {
        $negocio = Negocio::factory()->pro()->create();
        $suscripcion = Suscripcion::factory()->create([
            'negocio_id' => $negocio->id, 'estado' => 'gracia', 'vigente_hasta' => now()->subDays(5)->toDateString(),
        ]);

        app(ActualizarVigenciaSuscripciones::class)->handle(app(CobroService::class), app(SuscripcionService::class));

        $this->assertSame('gracia', $suscripcion->fresh()->estado);
        $this->assertTrue($negocio->fresh()->esPro());
    }

    public function test_una_suscripcion_en_gracia_que_paso_el_margen_vence_y_baja_el_negocio(): void
    {
        $negocio = Negocio::factory()->pro()->create();
        $suscripcion = Suscripcion::factory()->create([
            'negocio_id' => $negocio->id, 'estado' => 'gracia', 'vigente_hasta' => now()->subDays(11)->toDateString(),
        ]);

        app(ActualizarVigenciaSuscripciones::class)->handle(app(CobroService::class), app(SuscripcionService::class));

        $this->assertSame('vencida', $suscripcion->fresh()->estado);
        $this->assertFalse($negocio->fresh()->esPro());
    }

    public function test_una_suscripcion_cancelada_que_se_cumplio_baja_el_negocio_sin_cobro_nuevo(): void
    {
        $negocio = Negocio::factory()->pro()->create();
        $suscripcion = Suscripcion::factory()->create([
            'negocio_id' => $negocio->id, 'estado' => 'cancelada', 'vigente_hasta' => now()->subDay()->toDateString(),
        ]);

        app(ActualizarVigenciaSuscripciones::class)->handle(app(CobroService::class), app(SuscripcionService::class));

        $this->assertFalse($negocio->fresh()->esPro());
        $this->assertSame(0, Cobro::where('suscripcion_id', $suscripcion->id)->count());
        // El estado de la suscripción en sí no cambia, solo el plan del negocio.
        $this->assertSame('cancelada', $suscripcion->fresh()->estado);
    }

    public function test_una_suscripcion_activa_que_aun_no_se_cumple_no_cambia(): void
    {
        $negocio = Negocio::factory()->pro()->create();
        $suscripcion = Suscripcion::factory()->create([
            'negocio_id' => $negocio->id, 'estado' => 'activa', 'vigente_hasta' => now()->addDays(20)->toDateString(),
        ]);

        app(ActualizarVigenciaSuscripciones::class)->handle(app(CobroService::class), app(SuscripcionService::class));

        $this->assertSame('activa', $suscripcion->fresh()->estado);
        $this->assertSame(0, Cobro::where('suscripcion_id', $suscripcion->id)->count());
    }
}
