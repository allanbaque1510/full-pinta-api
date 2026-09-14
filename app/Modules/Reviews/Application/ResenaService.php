<?php

namespace App\Modules\Reviews\Application;

use App\Models\Cita;
use App\Models\Local;
use App\Models\Resena;
use App\Modules\Reviews\Events\ResenaCreada;
use App\Modules\Reviews\Events\ResenaRespondida;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reseñas (§4.8): una por cita, solo si `completada` y dentro de los 14 días
 * (`Cita::admiteResena()`, ya resuelto desde que se creó el modelo).
 */
final readonly class ResenaService
{
    /**
     * @param  array{
     *     puntaje_local: int, puntaje_profesional?: ?int,
     *     puntualidad?: ?int, limpieza?: ?int, comentario?: ?string,
     * }  $datos
     */
    public function crear(Cita $cita, array $datos): Resena
    {
        if (! $cita->admiteResena()) {
            throw_validacion(
                'Esta cita no admite reseña: debe estar completada y dentro de los 14 días siguientes.',
                'cita_id',
            );
        }

        if ($cita->resena()->exists()) {
            throw_validacion('Esta cita ya tiene una reseña.', 'cita_id');
        }

        $resena = Resena::create([
            'cita_id' => $cita->id,
            'local_id' => $cita->local_id,
            'profesional_id' => $cita->profesional_id,
            // DEFAULT en Postgres, no en PHP — ver skill `migracion`.
            'estado' => 'publicada',
            ...$datos,
        ]);

        ResenaCreada::dispatch($resena->id);

        return $resena;
    }

    public function responder(Resena $resena, string $respuesta): Resena
    {
        $resena->update(['respuesta_local' => $respuesta, 'respuesta_at' => now()]);

        ResenaRespondida::dispatch($resena->id);

        return $resena;
    }

    /** Listado para el staff del local: incluye todos los `estado`, no solo `publicada`. */
    public function listarPorLocal(Local $local): Collection
    {
        return $local->resenas()->latest()->get();
    }
}
