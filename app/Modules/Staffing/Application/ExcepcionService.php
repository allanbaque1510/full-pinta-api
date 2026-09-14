<?php

namespace App\Modules\Staffing\Application;

use App\Models\Excepcion;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\Recurso;
use App\Modules\Staffing\Events\ExcepcionModificada;
use Illuminate\Database\Eloquent\Collection;

/**
 * `Excepcion` (§4.6): cierres, ausencias y mantenimientos.
 *
 * Una ausencia de PROFESIONAL (`profesional_id` con `local_id` NULL) lo
 * bloquea en TODOS sus locales — no está enfermo solo en una sucursal. Por
 * eso hay tres formas de crearla, una por objetivo, en vez de un único
 * `crear()` genérico que reciba "cuál de los tres" como parámetro: cada una
 * expresa una intención distinta y el nombre del método ya lo dice.
 */
final readonly class ExcepcionService
{
    public function listarDeLocal(Local $local): Collection
    {
        return $local->excepciones;
    }

    public function listarDeProfesional(Profesional $profesional): Collection
    {
        return $profesional->excepciones;
    }

    public function listarDeRecurso(Recurso $recurso): Collection
    {
        return $recurso->excepciones;
    }

    public function crearParaLocal(Local $local, array $datos): Excepcion
    {
        $excepcion = Excepcion::create(['local_id' => $local->id, ...$datos]);

        ExcepcionModificada::dispatch(localId: $excepcion->local_id);

        return $excepcion;
    }

    public function crearParaProfesional(Profesional $profesional, array $datos): Excepcion
    {
        $excepcion = Excepcion::create(['profesional_id' => $profesional->id, ...$datos]);

        ExcepcionModificada::dispatch(profesionalId: $excepcion->profesional_id);

        return $excepcion;
    }

    public function crearParaRecurso(Recurso $recurso, array $datos): Excepcion
    {
        $excepcion = Excepcion::create(['recurso_id' => $recurso->id, ...$datos]);

        ExcepcionModificada::dispatch(recursoId: $excepcion->recurso_id);

        return $excepcion;
    }

    public function eliminar(Excepcion $excepcion): void
    {
        $excepcion->delete();

        ExcepcionModificada::dispatch($excepcion->local_id, $excepcion->profesional_id, $excepcion->recurso_id);
    }
}
