<?php

namespace Tests\Feature\Billing;

use App\Models\Cita;
use App\Models\CitaItem;
use App\Models\CitaProducto;
use App\Models\Local;
use App\Models\Negocio;
use App\Models\NegocioMiembro;
use App\Models\Profesional;
use App\Models\Usuario;
use App\Modules\Billing\Application\LiquidacionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * Liquidación de comisiones (§4.10, §10.2): sale del precio y la comisión ya
 * congelados en `cita_item`/`cita_producto`, con lock del periodo (§5.3).
 */
class LiquidacionTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_genera_el_borrador_con_los_montos_correctos(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();

        $cita = Cita::factory()->completada()->create([
            'local_id' => $local->id, 'profesional_id' => $profesional->id,
            'completada_at' => now()->subDays(2), 'propina' => 15,
        ]);
        CitaItem::factory()->create(['cita_id' => $cita->id, 'precio' => 100, 'comisionable' => true, 'comision_pct' => 50]);
        CitaItem::factory()->create(['cita_id' => $cita->id, 'precio' => 20, 'comisionable' => false, 'comision_pct' => 50]);
        CitaProducto::factory()->create(['cita_id' => $cita->id, 'cantidad' => 2, 'precio' => 10, 'comision_pct' => 10]);

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/liquidaciones", [
                'profesional_id' => $profesional->id,
                'periodo_desde' => now()->startOfMonth()->toDateString(),
                'periodo_hasta' => now()->endOfMonth()->toDateString(),
            ])
            ->assertCreated();

        // Free (por defecto): solo el total, sin desglose.
        $respuesta->assertJsonPath('estado', 'borrador')
            ->assertJsonPath('total_propinas', '15.00')
            ->assertJsonPath('total_a_pagar', '67.00') // 15 propina + 50 comision_servicios + 2 comision_productos
            ->assertJsonMissingPath('total_servicios')
            ->assertJsonMissingPath('comision_servicios');
    }

    public function test_el_plan_pro_ve_el_desglose_completo(): void
    {
        $negocio = Negocio::factory()->pro()->create();
        $usuario = Usuario::factory()->create();
        NegocioMiembro::factory()->propietario()->create(['usuario_id' => $usuario->id, 'negocio_id' => $negocio->id, 'local_id' => null]);
        $local = Local::factory()->create(['negocio_id' => $negocio->id]);
        $profesional = Profesional::factory()->create();

        $cita = Cita::factory()->completada()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        CitaItem::factory()->create(['cita_id' => $cita->id, 'precio' => 40, 'comisionable' => true, 'comision_pct' => 50]);

        $this->withHeader('Authorization', "Bearer {$usuario->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/locales/{$local->id}/liquidaciones", [
                'profesional_id' => $profesional->id,
                'periodo_desde' => now()->startOfMonth()->toDateString(),
                'periodo_hasta' => now()->endOfMonth()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('total_servicios', '40.00')
            ->assertJsonPath('comision_servicios', '20.00');
    }

    public function test_regenerar_el_borrador_actualiza_los_montos(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        $desde = now()->startOfMonth()->toDateString();
        $hasta = now()->endOfMonth()->toDateString();

        $cita = Cita::factory()->completada()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        CitaItem::factory()->create(['cita_id' => $cita->id, 'precio' => 10, 'comisionable' => true, 'comision_pct' => 50]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/liquidaciones", ['profesional_id' => $profesional->id, 'periodo_desde' => $desde, 'periodo_hasta' => $hasta])
            ->assertJsonPath('total_a_pagar', '5.00');

        // Se agrega otro servicio a la misma cita antes de regenerar.
        CitaItem::factory()->create(['cita_id' => $cita->id, 'precio' => 30, 'comisionable' => true, 'comision_pct' => 50]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/liquidaciones", ['profesional_id' => $profesional->id, 'periodo_desde' => $desde, 'periodo_hasta' => $hasta])
            ->assertJsonPath('total_a_pagar', '20.00');

        $this->assertDatabaseCount('liquidacion', 1);
    }

    public function test_cerrar_una_liquidacion_y_no_se_puede_regenerar_ni_cerrar_de_nuevo(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        $desde = now()->startOfMonth()->toDateString();
        $hasta = now()->endOfMonth()->toDateString();

        $liquidacionId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/liquidaciones", ['profesional_id' => $profesional->id, 'periodo_desde' => $desde, 'periodo_hasta' => $hasta])
            ->json('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/liquidaciones/{$liquidacionId}/cerrar")
            ->assertOk()
            ->assertJsonPath('estado', 'cerrada');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/liquidaciones/{$liquidacionId}/cerrar")
            ->assertUnprocessable();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/liquidaciones", ['profesional_id' => $profesional->id, 'periodo_desde' => $desde, 'periodo_hasta' => $hasta])
            ->assertUnprocessable();
    }

    public function test_marcar_pagada_solo_desde_cerrada(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();

        $liquidacionId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/liquidaciones", [
                'profesional_id' => $profesional->id,
                'periodo_desde' => now()->startOfMonth()->toDateString(),
                'periodo_hasta' => now()->endOfMonth()->toDateString(),
            ])->json('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/liquidaciones/{$liquidacionId}/marcar-pagada")
            ->assertUnprocessable();

        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/liquidaciones/{$liquidacionId}/cerrar")->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/liquidaciones/{$liquidacionId}/marcar-pagada")
            ->assertOk()
            ->assertJsonPath('estado', 'pagada');
    }

    public function test_recepcion_no_puede_gestionar_liquidaciones(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $tokenRecepcion] = $this->recepcionEnNegocio($negocio);

        $this->withHeader('Authorization', "Bearer {$tokenRecepcion}")
            ->getJson("/api/v1/locales/{$local->id}/liquidaciones")
            ->assertForbidden();
    }

    public function test_generar_borrador_toma_el_lock_del_periodo(): void
    {
        $local = Local::factory()->create();
        $profesional = Profesional::factory()->create();
        $capturadas = [];

        DB::listen(function ($query) use (&$capturadas) {
            if (str_contains(strtolower($query->sql), 'for update')) {
                $capturadas[] = $query->sql;
            }
        });

        app(LiquidacionService::class)->generarBorrador(
            $local, $profesional, CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()->endOfMonth(),
        );

        $this->assertNotEmpty($capturadas, 'Se esperaba un SELECT ... FOR UPDATE (§5.3, "aquí sí es plata").');
    }
}
