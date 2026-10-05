<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permiso (`permiso`): una acción que `ContextoAcceso::tienePermiso()` puede
 * conceder o negar según el rol (§3.2). Ver el docblock de la migración
 * `crear_tabla_permisos` para el alcance exacto.
 */
class Permiso extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'permiso';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permiso');
    }
}
