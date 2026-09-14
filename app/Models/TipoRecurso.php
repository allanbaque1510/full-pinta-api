<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TipoRecurso (`tipo_recurso`): silla, mesa_unas, lavacabezas, tina,
 * box_privado, ninguno.
 *
 * Tabla de parámetros — referenciada por id desde `catalogo_servicio` (qué
 * tipo de recurso necesita el servicio) y desde `recurso` (Staffing: qué tipo
 * ES esa unidad física), para que el agendamiento pueda casar uno contra el
 * otro (§4.5, §4.6). `ninguno` solo aplica a servicios: un recurso físico
 * siempre es algo concreto.
 */
class TipoRecurso extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tipo_recurso';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function serviciosCatalogo(): HasMany
    {
        return $this->hasMany(CatalogoServicio::class, 'tipo_recurso_id');
    }

    public function recursos(): HasMany
    {
        return $this->hasMany(Recurso::class, 'tipo_recurso_id');
    }
}
