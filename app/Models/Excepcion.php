<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Excepcion (`excepcion`).
 *
 * Una ausencia del profesional (`profesional_id` con `local_id` NULL) lo bloquea
 * en TODOS sus locales: no está enfermo solo en una sucursal (§4.6).
 */
class Excepcion extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'excepcion';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'immutable_datetime',
            'fecha_fin' => 'immutable_datetime',
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

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(Recurso::class, 'recurso_id');
    }
}
