<?php

namespace Tests\Feature\Billing;

use App\Models\Cobro;
use App\Models\Suscripcion;
use App\Models\Usuario;
use App\Modules\Billing\Application\FakeEmisorComprobanteSri;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

class CobroTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeEmisorComprobanteSri::reset();
    }

    public function test_marcar_pagado_pide_un_comprobante_al_sri(): void
    {
        [, $token, $negocio] = $this->propietarioConNegocio();
        $suscripcion = Suscripcion::factory()->create(['negocio_id' => $negocio->id]);
        $cobro = Cobro::factory()->create(['suscripcion_id' => $suscripcion->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/cobros/{$cobro->id}/marcar-pagado")
            ->assertOk()
            ->assertJsonPath('estado', 'pagado')
            ->assertJsonPath('comprobante_sri', fn ($valor) => $valor !== null);

        $this->assertCount(1, FakeEmisorComprobanteSri::emitidos());
    }

    public function test_lista_los_cobros_del_negocio(): void
    {
        [, $token, $negocio] = $this->propietarioConNegocio();
        $suscripcion = Suscripcion::factory()->create(['negocio_id' => $negocio->id]);
        Cobro::factory()->create(['suscripcion_id' => $suscripcion->id]);
        Cobro::factory()->create(['suscripcion_id' => $suscripcion->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/negocios/{$negocio->id}/cobros")
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_un_extrano_no_puede_marcar_pagado_un_cobro_ajeno(): void
    {
        $cobro = Cobro::factory()->create();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/cobros/{$cobro->id}/marcar-pagado")
            ->assertForbidden();
    }
}
