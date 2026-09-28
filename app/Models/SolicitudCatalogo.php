<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SolicitudCatalogo (`solicitud_catalogo`).
 *
 * Cómo un local pide que la plataforma agregue un servicio que falta.
 */
class SolicitudCatalogo extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'solicitud_catalogo';

    protected $guarded = ['id'];

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitante_id');
    }

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class, 'rubro_id');
    }

    public function catalogoServicio(): BelongsTo
    {
        return $this->belongsTo(CatalogoServicio::class, 'catalogo_servicio_id');
    }
}
