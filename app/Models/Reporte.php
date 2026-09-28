<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Reporte (`reporte`).
 *
 * `objeto_type` + `objeto_id` es polimórfico a propósito: se reporta
 * cualquier cosa. Sin FK — no se valida que el objeto exista (§4.8).
 */
class Reporte extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'reporte';

    protected $guarded = ['id'];

    public function reportante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'reportante_id');
    }

    public function objeto(): MorphTo
    {
        return $this->morphTo();   // cero configuración — objeto_type/objeto_id ya siguen la convención
    }
}
