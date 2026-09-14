<?php

namespace Tests\Feature\Reviews;

use App\Models\Cita;
use App\Models\Local;
use App\Models\Resena;
use App\Modules\Reviews\Jobs\RecalcularScoreRanking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ranking orgánico (§7.1-7.3): bayesiano dominante, corregido por actividad,
 * confiabilidad, completitud y verificación. Job nocturno, nunca en request.
 */
class RecalcularScoreRankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_local_sin_resenas_recibe_el_promedio_global_puro(): void
    {
        $conResenas = Local::factory()->create();
        $cita = Cita::factory()->completada()->create(['local_id' => $conResenas->id]);
        Resena::factory()->create(['cita_id' => $cita->id, 'puntaje_local' => 5]);

        $sinResenas = Local::factory()->create();

        app(RecalcularScoreRanking::class)->handle();

        // Sin reseñas propias: el bayesiano cae exactamente en el promedio
        // global (única reseña publicada, puntaje_local = 5). Sin citas en
        // los últimos 30 días, la confiabilidad por defecto es 1.0 (no hay
        // cancelaciones que penalizar) — 5 (bayesiano) + 1 (confiabilidad).
        $this->assertEquals(6.0, round($sinResenas->fresh()->score_ranking, 1));
    }

    public function test_muchas_resenas_altas_pesan_mas_que_pocas_hacia_el_promedio_local(): void
    {
        $global = Local::factory()->create();
        // Ancla el promedio global bajo, para que el efecto sea visible.
        $citaAncla = Cita::factory()->completada()->create(['local_id' => $global->id]);
        Resena::factory()->create(['cita_id' => $citaAncla->id, 'puntaje_local' => 1]);

        $conMuchasBuenas = Local::factory()->create();
        for ($i = 0; $i < 20; $i++) {
            $cita = Cita::factory()->completada()->create(['local_id' => $conMuchasBuenas->id]);
            Resena::factory()->create(['cita_id' => $cita->id, 'puntaje_local' => 5]);
        }

        app(RecalcularScoreRanking::class)->handle();

        // Con 20 reseñas de 5, el bayesiano ya está mucho más cerca de 5 que
        // del promedio global (que incluye el ancla de 1).
        $this->assertGreaterThan(4.0, $conMuchasBuenas->fresh()->score_ranking);
    }

    public function test_verificado_suma_un_bono_por_encima_de_uno_no_verificado(): void
    {
        $sinVerificar = Local::factory()->create(['verificado' => false]);
        $verificado = Local::factory()->create(['verificado' => true]);

        app(RecalcularScoreRanking::class)->handle();

        $this->assertGreaterThan(
            $sinVerificar->fresh()->score_ranking,
            $verificado->fresh()->score_ranking,
        );
    }

    public function test_las_cancelaciones_del_local_penalizan_el_score(): void
    {
        $confiable = Local::factory()->create();
        Cita::factory()->completada()->create(['local_id' => $confiable->id, 'inicio' => now()->subDays(5)]);

        $pocoConfiable = Local::factory()->create();
        Cita::factory()->cancelada('local')->create(['local_id' => $pocoConfiable->id, 'inicio' => now()->subDays(5)]);

        app(RecalcularScoreRanking::class)->handle();

        $this->assertGreaterThan(
            $pocoConfiable->fresh()->score_ranking,
            $confiable->fresh()->score_ranking,
        );
    }

    public function test_no_toca_locales_que_no_estan_activos(): void
    {
        $borrador = Local::factory()->borrador()->create(['score_ranking' => 1.2345]);

        app(RecalcularScoreRanking::class)->handle();

        $this->assertEquals(1.2345, (float) $borrador->fresh()->score_ranking);
    }
}
