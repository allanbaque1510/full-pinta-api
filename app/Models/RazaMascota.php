<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RazaMascota (`raza_mascota`): razas por especie, incluye "Mestizo" como fila normal.
 *
 * Tabla de parámetros — referenciada por id desde `mascota`.
 */
class RazaMascota extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raza_mascota';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(EspecieMascota::class, 'especie_id');
    }

    public function mascotas(): HasMany
    {
        return $this->hasMany(Mascota::class, 'raza_id');
    }
}
