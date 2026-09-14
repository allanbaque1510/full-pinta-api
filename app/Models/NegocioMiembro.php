<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NegocioMiembro (`negocio_miembro`).
 *
 * Recepción es un rol aparte, no un propietario con menos permisos: agenda y
 * cobra, pero no ve las comisiones de nadie (§3.2).
 *
 * `local_id` NULL significa todos los locales del negocio.
 */
class NegocioMiembro extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'negocio_miembro';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'desde' => 'date',
            'hasta' => 'date',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class, 'negocio_id');
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    public function scopeVigente(Builder $query): Builder
    {
        return $query->whereDate('desde', '<=', now())
            ->where(fn ($q) => $q->whereNull('hasta')->orWhereDate('hasta', '>=', now()));
    }
}
