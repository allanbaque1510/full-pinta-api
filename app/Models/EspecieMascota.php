<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * EspecieMascota (`especie_mascota`): perro, gato, otro.
 *
 * Tabla de parámetros — referenciada por id desde `mascota` y desde
 * `raza_mascota`, que agrupa sus razas por especie.
 */
class EspecieMascota extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'especie_mascota';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function razas(): HasMany
    {
        return $this->hasMany(RazaMascota::class, 'especie_id');
    }

    public function mascotas(): HasMany
    {
        return $this->hasMany(Mascota::class, 'especie_id');
    }
}
