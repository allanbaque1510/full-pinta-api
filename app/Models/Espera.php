<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Espera (`espera`).
 *
 * Lista de espera: cuesta poco, retiene mucho y convierte cancelaciones en citas.
 * En barbería los sábados se llenan (§4.7).
 */
class Espera extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'espera';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha_deseada' => 'date',
        ];
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cliente_id');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    public function servicioLocal(): BelongsTo
    {
        return $this->belongsTo(ServicioLocal::class, 'servicio_local_id');
    }
}
