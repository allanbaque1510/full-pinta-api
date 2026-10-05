<?php

namespace Tests\Feature\Identity;

use App\Models\Local;
use App\Models\Negocio;
use App\Models\NegocioMiembro;
use App\Models\Usuario;
use App\Support\Auth\ContextoAcceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La matriz de permisos del §3.2, aplicada vía `ContextoAcceso::tienePermiso()`
 * (sin Policy ni Gate — ver `App\Http\Middleware\VerificarPermiso`). El caso
 * que existe para probar es el de recepción: agenda y cobra, pero no ve
 * comisiones de nadie — es literalmente el motivo por el que ese rol existe.
 */
class ContextoAccesoTest extends TestCase
{
    use RefreshDatabase;

    private function local(): Local
    {
        $negocio = Negocio::factory()->create();

        return Local::factory()->create(['negocio_id' => $negocio->id]);
    }

    private function conRol(Local $local, string $rol): Usuario
    {
        $usuario = Usuario::factory()->create();

        NegocioMiembro::factory()->create([
            'usuario_id' => $usuario->id,
            'negocio_id' => $local->negocio_id,
            'local_id' => null,
            'rol_personal' => $rol,
        ]);

        return $usuario;
    }

    public function test_propietario_puede_todo_incluida_la_suscripcion(): void
    {
        $local = $this->local();
        $usuario = Usuario::find($local->negocio->propietario_id);
        NegocioMiembro::factory()->propietario()->create([
            'usuario_id' => $usuario->id,
            'negocio_id' => $local->negocio_id,
            'local_id' => null,
        ]);

        $acceso = new ContextoAcceso($usuario);

        $this->assertSame('propietario', $acceso->rolEnLocal($local));
        $this->assertTrue($acceso->tienePermiso($local, 'locales.clientes.index'));
        $this->assertTrue($acceso->tienePermiso($local, 'locales.liquidaciones.index'));
        $this->assertTrue($acceso->tienePermiso($local, 'locales.update'));
        $this->assertTrue($acceso->tienePermiso($local, 'locales.servicios.store'));
        $this->assertTrue($acceso->puedeGestionarSuscripcion($local->negocio));
    }

    public function test_admin_puede_todo_menos_gestionar_la_suscripcion(): void
    {
        $local = $this->local();
        $usuario = $this->conRol($local, 'admin');

        $acceso = new ContextoAcceso($usuario);

        $this->assertTrue($acceso->tienePermiso($local, 'locales.clientes.index'));
        $this->assertTrue($acceso->tienePermiso($local, 'locales.liquidaciones.index'));
        $this->assertTrue($acceso->tienePermiso($local, 'locales.update'));
        $this->assertTrue($acceso->tienePermiso($local, 'locales.servicios.store'));
        $this->assertFalse($acceso->puedeGestionarSuscripcion($local->negocio));
    }

    /**
     * El caso que justifica el rol: agenda y cobra, pero las comisiones de
     * los demás no son asunto suyo.
     */
    public function test_recepcion_ve_la_agenda_pero_no_las_comisiones_de_todos(): void
    {
        $local = $this->local();
        $usuario = $this->conRol($local, 'recepcion');

        $acceso = new ContextoAcceso($usuario);

        $this->assertTrue($acceso->tienePermiso($local, 'locales.clientes.index'));
        $this->assertFalse($acceso->tienePermiso($local, 'locales.liquidaciones.index'));
        $this->assertFalse($acceso->tienePermiso($local, 'locales.update'));
        $this->assertFalse($acceso->tienePermiso($local, 'locales.servicios.store'));
        $this->assertFalse($acceso->puedeGestionarSuscripcion($local->negocio));
    }

    public function test_un_desconocido_sin_membresia_no_puede_nada(): void
    {
        $local = $this->local();
        $usuario = Usuario::factory()->create();

        $acceso = new ContextoAcceso($usuario);

        $this->assertNull($acceso->rolEnLocal($local));
        $this->assertFalse($acceso->tienePermiso($local, 'locales.clientes.index'));
        $this->assertFalse($acceso->tienePermiso($local, 'locales.liquidaciones.index'));
    }

    public function test_una_membresia_de_otro_negocio_no_da_acceso_a_este_local(): void
    {
        $local = $this->local();
        $otroNegocio = Negocio::factory()->create();

        $usuario = Usuario::factory()->create();
        NegocioMiembro::factory()->propietario()->create([
            'usuario_id' => $usuario->id,
            'negocio_id' => $otroNegocio->id,
            'local_id' => null,
        ]);

        $this->assertNull((new ContextoAcceso($usuario))->rolEnLocal($local));
    }

    public function test_una_membresia_restringida_a_otro_local_no_aplica_aqui(): void
    {
        $negocio = Negocio::factory()->create();
        $localA = Local::factory()->create(['negocio_id' => $negocio->id]);
        $localB = Local::factory()->create(['negocio_id' => $negocio->id]);

        $usuario = Usuario::factory()->create();
        NegocioMiembro::factory()->create([
            'usuario_id' => $usuario->id,
            'negocio_id' => $negocio->id,
            'local_id' => $localA->id,
            'rol_personal' => 'recepcion',
        ]);

        $acceso = new ContextoAcceso($usuario);

        $this->assertSame('recepcion', $acceso->rolEnLocal($localA));
        $this->assertNull($acceso->rolEnLocal($localB));
    }
}
