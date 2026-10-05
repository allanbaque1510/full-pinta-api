<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Rol (`rol`): los cinco roles de acceso de la matriz del §3.2 — cliente,
 * profesional, recepcion, propietario y admin — con sus permisos
 * configurables en `rol_permiso`. Ver el docblock de la migración
 * `crear_tabla_permisos` para lo que esta tabla deliberadamente no cubre
 * (propiedad puntual sobre un registro, y la gestión de suscripción).
 *
 * No confundir con `negocio_miembro.rol_personal` (acceso real que tiene un
 * usuario a la plataforma: propietario/admin/recepcion) ni con
 * `asignacion.rol_personal` (puesto de trabajo de un profesional: barbero,
 * estilista...) — ambas columnas se renombraron a `rol_personal`
 * precisamente para no competir por el mismo nombre con esta tabla.
 */
class Rol extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rol';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso');
    }
}
