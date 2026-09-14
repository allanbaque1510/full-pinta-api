<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CatalogoServicio (`catalogo_servicio`).
 *
 * Lo define la plataforma, no los locales. Si cada local escribe sus servicios en
 * texto libre, la búsqueda y el filtro de precios mueren (§4.5).
 */
class CatalogoServicio extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'catalogo_servicio';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'duracion_base_min' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function serviciosLocal(): HasMany
    {
        return $this->hasMany(ServicioLocal::class, 'catalogo_servicio_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(ServicioCategoria::class, 'categoria_id');
    }

    public function tipoRecurso(): BelongsTo
    {
        return $this->belongsTo(TipoRecurso::class, 'tipo_recurso_id');
    }

    /**
     * La vertical se deriva de la categoría (`categoria->vertical`) — no se
     * guarda aparte en esta tabla para no duplicar el dato (§4.5). Cargar con
     * `->with('categoria.vertical')` para leerla sin lazy loading.
     */
}
