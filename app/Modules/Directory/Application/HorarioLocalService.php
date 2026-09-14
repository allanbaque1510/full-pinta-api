<?php

namespace App\Modules\Directory\Application;

use App\Models\HorarioLocal;
use App\Models\Local;
use Illuminate\Database\Eloquent\Collection;

/**
 * Todo lo que se puede hacer con un `HorarioLocal` (§4.4): varias filas por
 * día permiten partir jornada (mañana/tarde). No hay límite de filas por día
 * a nivel de aplicación — el `CHECK (cierra > abre)` de la base es la única
 * regla dura por fila; que no se traslapen dos franjas del mismo día es
 * responsabilidad de quien carga el horario, no algo que valga la pena
 * calcular aquí para un local con dos o tres franjas.
 */
final readonly class HorarioLocalService
{
    public function listar(Local $local): Collection
    {
        return $local->horarios;
    }

    public function crear(Local $local, array $datos): HorarioLocal
    {
        if ($datos['cierra'] <= $datos['abre']) {
            throw_validacion('La hora de cierre debe ser posterior a la de apertura.', 'cierra');
        }

        return $local->horarios()->create($datos);
    }

    public function actualizar(HorarioLocal $horario, array $datos): HorarioLocal
    {
        // El valor que ya está en el modelo viene de Postgres con segundos
        // ("09:00:00"); el que llega del request es "H:i" ("09:00"). Se
        // recorta a los primeros 5 caracteres antes de comparar — si no, un
        // PATCH que solo cambia una de las dos horas compara formatos
        // distintos y "09:00" queda (mal) por delante de "09:00:00".
        $abre = substr($datos['abre'] ?? $horario->abre, 0, 5);
        $cierra = substr($datos['cierra'] ?? $horario->cierra, 0, 5);

        if ($cierra <= $abre) {
            throw_validacion('La hora de cierre debe ser posterior a la de apertura.', 'cierra');
        }

        $horario->update($datos);

        return $horario;
    }

    public function eliminar(HorarioLocal $horario): void
    {
        // Tabla sin `activo`/`estado`: no es de las que se marcan, es de las
        // que de verdad se borran (§4.2, ver skill `migracion`).
        $horario->delete();
    }
}
