<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Recurso (`recurso`).
 *
 * UNA FILA POR UNIDAD FÍSICA, nunca una fila con `cantidad = 3`. Con un campo
 * cantidad el constraint de exclusión subvende o, si se relaja, sobrevende.
 * Además permite marcar una mesa fuera de servicio sin afectar las otras (§4.6).
 */
class Recurso extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'recurso';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    public function tipoRecurso(): BelongsTo
    {
        return $this->belongsTo(TipoRecurso::class, 'tipo_recurso_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'recurso_id');
    }

    public function excepciones(): HasMany
    {
        return $this->hasMany(Excepcion::class, 'recurso_id');
    }
}
