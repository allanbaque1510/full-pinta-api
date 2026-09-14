<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Habilidad (`habilidad`).
 *
 * La tabla que todos olvidan y la que rompe el agendamiento. No todos los
 * barberos hacen todo: si el cliente agenda uñas y el sistema le asigna al
 * barbero que solo hace fades, hay problema el día uno (§4.6).
 */
class Habilidad extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'habilidad';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'precio_override' => 'decimal:2',
        ];
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
