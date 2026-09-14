<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resena (`resena`).
 *
 * Una reseña por cita (`UNIQUE cita_id`). Sin eso, el barbero se autoreseña y el
 * ranking no vale nada. Solo se puede reseñar una cita `completada`, dentro de
 * los 14 días siguientes (§4.8).
 */
class Resena extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'resena';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'puntaje_local' => 'integer',
            'puntaje_profesional' => 'integer',
            'puntualidad' => 'integer',
            'limpieza' => 'integer',
            'respuesta_at' => 'immutable_datetime',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    public function scopePublicadas(Builder $query): Builder
    {
        return $query->where('estado', 'publicada');
    }
}
