<?php

namespace Tests\Feature\Scheduling;

use App\Models\Cita;
use App\Modules\Scheduling\Application\Transiciones\ExpirarHold;
use App\Modules\Scheduling\Jobs\ExpirarHoldsVencidos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "No confiar en que el job corrió" (§5.4) exige el filtro en cada consulta,
 * pero el job igual tiene que limpiar los holds vencidos de verdad (§6.8).
 */
class ExpirarHoldsVencidosTest extends TestCase
{
    use RefreshDatabase;

    public function test_expira_los_holds_vencidos_y_deja_intactos_los_vigentes(): void
    {
        $vencido = Cita::factory()->holdVencido()->create();
        $vigente = Cita::factory()->reservada()->create();
        $yaConfirmada = Cita::factory()->create(); // no es un hold

        app(ExpirarHoldsVencidos::class)->handle(app(ExpirarHold::class));

        $this->assertDatabaseHas('cita', ['id' => $vencido->id, 'estado' => 'expirada']);
        $this->assertDatabaseHas('cita', ['id' => $vigente->id, 'estado' => 'reservada']);
        $this->assertDatabaseHas('cita', ['id' => $yaConfirmada->id, 'estado' => 'confirmada']);
        $this->assertDatabaseHas('cita_evento', ['cita_id' => $vencido->id, 'estado_nuevo' => 'expirada']);
    }
}
