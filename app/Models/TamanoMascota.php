<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TamanoMascota (`tamano_mascota`): muy pequeño, pequeño, mediano, grande, gigante.
 *
 * Tabla de parámetros — referenciada por id desde `mascota` y desde
 * `servicio_local_tamano` (§4.3, §4.5): el mismo tamaño de animal define la
 * ficha de la mascota y el precio/duración que cobra el local por ese tamaño.
 */
class TamanoMascota extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tamano_mascota';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function mascotas(): HasMany
    {
        return $this->hasMany(Mascota::class, 'tamano_id');
    }
}
