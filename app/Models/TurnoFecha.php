<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TurnoFecha (`turno_fecha`).
 *
 * Overrides por fecha concreta ("este sábado no voy a Urdesa, voy a Alborada").
 * No modifica el turno recurrente: el cálculo aplica primero los recurrentes
 * vigentes y luego estos (§4.6).
 */
class TurnoFecha extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'turno_fecha';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }
}
