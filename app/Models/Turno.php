<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Turno (`turno`).
 *
 * `profesional_id` y `local_id` están desnormalizados SOLO para habilitar el
 * constraint `turno_sin_traslape` (§4.11). Se escriben en la misma transacción
 * que la asignación.
 *
 * `rango` y `vigencia` son columnas generadas por Postgres: se leen, nunca se
 * escriben.
 */
class Turno extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'turno';

    protected $guarded = ['id', 'rango', 'vigencia'];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
        ];
    }

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(Asignacion::class, 'asignacion_id');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    public function scopeVigenteEn(Builder $query, \DateTimeInterface|string $fecha): Builder
    {
        return $query->whereDate('vigente_desde', '<=', $fecha)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fecha));
    }
}
