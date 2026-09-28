<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TipoImagen (`tipo_imagen`): perfil, portada, fachada, interior, muestra.
 *
 * Tabla de parámetros — referenciada por id desde `imagen`, mismo criterio
 * que `tamano_mascota`/`metodo_pago`.
 */
class TipoImagen extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tipo_imagen';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(Imagen::class, 'tipo_id');
    }
}
