<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notificacion (`notificacion`).
 *
 * `UNIQUE (cita_id, tipo_evento, canal)` es el seguro contra duplicados por
 * reintentos de la cola. `costo_usd` por fila permite saber el costo real por
 * local (§11.3).
 */
class Notificacion extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'notificacion';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'programada_para' => 'immutable_datetime',
            'enviada_at' => 'immutable_datetime',
            'costo_usd' => 'decimal:5',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(NotificacionCategoria::class, 'categoria_id');
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', 'programada')->where('programada_para', '<=', now());
    }
}
