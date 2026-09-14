<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CitaEvento (`cita_evento`).
 *
 * Auditoría inmutable. Cuando el barbero diga "esa cita fue mía y no me la
 * pagaron", esto es la única respuesta posible. Para comisiones no es opcional:
 * es plata entre dos personas (§4.7).
 */
class CitaEvento extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cita_evento';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }
}
