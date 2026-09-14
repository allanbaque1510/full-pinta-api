<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DeviceToken (`device_token`).
 *
 * FCM devuelve UNREGISTERED o INVALID_ARGUMENT cuando el token muere: marcar
 * `activo = false`, no reintentar en bucle. Si no, la tasa de entrega miente
 * (§11.9).
 */
class DeviceToken extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'device_token';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'ultimo_uso_at' => 'immutable_datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
