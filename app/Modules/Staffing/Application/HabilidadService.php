<?php

namespace App\Modules\Staffing\Application;

use App\Models\Habilidad;
use App\Models\Profesional;
use Illuminate\Database\Eloquent\Collection;

/**
 * `Habilidad` (§4.6): qué servicios puede atender un profesional. La tabla
 * que todos olvidan y la que rompe el agendamiento — si el cliente agenda
 * uñas y el sistema le asigna al barbero que solo hace fades, hay problema
 * el día uno.
 */
final readonly class HabilidadService
{
    public function listarPorProfesional(Profesional $profesional): Collection
    {
        // Anidado: el Resource lee `servicioLocal.catalogoServicio.nombre` —
        // con `preventLazyLoading()` activo, cargar solo `servicioLocal`
        // rompería al acceder a `catalogoServicio` desde ahí.
        return $profesional->habilidades()->with('servicioLocal.catalogoServicio')->get();
    }

    public function crear(Profesional $profesional, array $datos): Habilidad
    {
        if ($profesional->habilidades()->where('servicio_local_id', $datos['servicio_local_id'])->exists()) {
            throw_validacion('Este profesional ya tiene esa habilidad registrada.', 'servicio_local_id');
        }

        return $profesional->habilidades()->create($datos);
    }

    /** Sin `activo`: la tabla no lo tiene, se borra de verdad (§4.2). */
    public function eliminar(Habilidad $habilidad): void
    {
        $habilidad->delete();
    }
}
