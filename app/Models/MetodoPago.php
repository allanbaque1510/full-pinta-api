<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MetodoPago (`metodo_pago`): efectivo, transferencia, tarjeta, payphone.
 *
 * Tabla de parámetros — referenciada por id desde `local_metodo_pago` (los que
 * acepta cada local) y desde `cita` (con cuál se pagó). Antes era la categoría
 * 'pago' de `amenidad` y un varchar+CHECK en `cita`, repetidos (§4.4).
 */
class MetodoPago extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'metodo_pago';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function locales(): BelongsToMany
    {
        return $this->belongsToMany(Local::class, 'local_metodo_pago');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'metodo_pago_id');
    }
}
