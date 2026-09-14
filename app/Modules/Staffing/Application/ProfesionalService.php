<?php

namespace App\Modules\Staffing\Application;

use App\Models\Asignacion;
use App\Models\Favorito;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\Usuario;
use App\Modules\Staffing\Http\Resources\ProfesionalPublicoResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Todo lo que se puede hacer con un `Profesional` (§4.6).
 *
 * Un profesional no pertenece a un solo local — puede trabajar en varios a la
 * vez (Kevin en Alborada y en Urdesa). Por eso "darlo de alta" siempre pasa
 * por un local concreto: se crea el perfil Y su primera `Asignacion` en la
 * misma transacción, igual que `NegocioService::crear()` provisiona la
 * membresía del propietario. Un profesional que YA existe se suma a un
 * segundo local con `AsignacionService::crear()`, sin tocar este servicio.
 */
final readonly class ProfesionalService
{
    /** Profesionales con asignación vigente en este local — el roster que ve la agenda. */
    public function listarPorLocal(Local $local): Collection
    {
        return Profesional::query()
            ->whereHas('asignaciones', fn ($q) => $q->where('local_id', $local->id)->vigente())
            ->get();
    }

    /**
     * @param  array{
     *     nombre: string, alias?: ?string, bio?: ?string, foto_url?: ?string,
     *     independiente?: bool, perfil_publico?: bool, traslado_min?: int,
     * }  $datosProfesional
     * @param  array{rol: string, modalidad: string, comision_pct: float, desde?: string}  $datosAsignacion
     */
    public function crearConAsignacion(Local $local, array $datosProfesional, array $datosAsignacion): Profesional
    {
        return DB::transaction(function () use ($local, $datosProfesional, $datosAsignacion) {
            $profesional = Profesional::create([
                // Con DEFAULT en Postgres, no en PHP — ver skill `migracion`.
                'independiente' => false,
                'perfil_publico' => true,
                'traslado_min' => 30,
                ...$datosProfesional,
            ]);

            Asignacion::create([
                'local_id' => $local->id,
                'profesional_id' => $profesional->id,
                'rol' => $datosAsignacion['rol'],
                'modalidad' => $datosAsignacion['modalidad'],
                'comision_pct' => $datosAsignacion['comision_pct'],
                'desde' => $datosAsignacion['desde'] ?? now()->toDateString(),
            ]);

            return $profesional;
        });
    }

    public function actualizar(Profesional $profesional, array $datos): Profesional
    {
        $profesional->update($datos);

        return $profesional;
    }

    /**
     * Perfil público (§7.5). 404, no 403, si `perfil_publico = false` — no hay
     * que confirmarle al público que el profesional existe pero está oculto.
     *
     * `$usuario` es el cliente autenticado, si lo hay (la ruta es pública, no
     * exige sesión) — resuelve `es_favorito`.
     *
     * Se arma el array ya resuelto por `ProfesionalPublicoResource` (con el
     * viaje de ida y vuelta por JSON: `->resolve()` no alcanza, ver el mismo
     * comentario en `LocalService::perfilPublico()`) y se agrega
     * `es_favorito` recién ahí, **fuera** del Resource. `ProfesionalPublicoResource`
     * se reusa en `FavoritoResource` sobre profesionales que nunca pasan por
     * aquí — si `es_favorito` se leyera dentro del Resource, esos casos
     * revientan con `MissingAttributeException` (el proyecto tiene acceso
     * estricto a atributos).
     *
     * @return array<string, mixed>
     */
    public function perfilPublico(Profesional $profesional, ?Usuario $usuario = null): array
    {
        if (! $profesional->perfil_publico) {
            throw new ModelNotFoundException;
        }

        $profesional->load([
            'fotos',
            'resenas' => fn ($q) => $q->publicadas(),
            'habilidades.servicioLocal.catalogoServicio',
        ]);

        $perfil = json_decode(json_encode(ProfesionalPublicoResource::make($profesional)), true);

        $perfil['es_favorito'] = $usuario !== null
            && Favorito::where('usuario_id', $usuario->id)->where('profesional_id', $profesional->id)->exists();

        return $perfil;
    }
}
