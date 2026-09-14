<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * NotificacionCategoria (`notificacion_categoria`): citas, agenda, social,
 * promos.
 *
 * Tabla de parámetros — referenciada por id desde `preferencia_notificacion`
 * y `notificacion` (§4.9), en vez del mismo varchar+CHECK repetido en las dos
 * tablas del módulo.
 */
class NotificacionCategoria extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'notificacion_categoria';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function preferencias(): HasMany
    {
        return $this->hasMany(PreferenciaNotificacion::class, 'categoria_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'categoria_id');
    }
}
