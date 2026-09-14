<?php

namespace App\Models;

use App\Models\Concerns\ClaveCompuesta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PreferenciaNotificacion (`preferencia_notificacion`).
 *
 * Se respeta siempre, SALVO para lo transaccional indispensable y el OTP. Nadie
 * se desuscribe de que le avisen que su cita se canceló (§11).
 */
class PreferenciaNotificacion extends Model
{
    use ClaveCompuesta, HasFactory;

    protected $table = 'preferencia_notificacion';

    protected $guarded = ['id'];

    /** @var array<int,string> */
    protected $clavePrimaria = ['usuario_id', 'categoria_id'];

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'push' => 'boolean',
            'whatsapp' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(NotificacionCategoria::class, 'categoria_id');
    }
}
