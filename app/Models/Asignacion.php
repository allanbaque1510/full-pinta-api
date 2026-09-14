<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Asignacion (`asignacion`).
 *
 * El vínculo laboral, SIN horarios. Un profesional puede tener varias
 * asignaciones vigentes a la vez (día en un local, noche en otro).
 */
class Asignacion extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'asignacion';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'comision_pct' => 'decimal:2',
            'desde' => 'date',
            'hasta' => 'date',
        ];
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'asignacion_id');
    }

    public function scopeVigente(Builder $query): Builder
    {
        return $query->whereDate('desde', '<=', now())
            ->where(fn ($q) => $q->whereNull('hasta')->orWhereDate('hasta', '>=', now()));
    }

    /**
     * Igual que `scopeVigente()` pero contra una fecha dada en vez de `now()` —
     * el motor de disponibilidad (§5) consulta días futuros, no solo "hoy".
     */
    public function scopeVigenteEn(Builder $query, \DateTimeInterface|string $fecha): Builder
    {
        return $query->whereDate('desde', '<=', $fecha)
            ->where(fn ($q) => $q->whereNull('hasta')->orWhereDate('hasta', '>=', $fecha));
    }
}
