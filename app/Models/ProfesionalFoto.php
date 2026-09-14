<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProfesionalFoto (`profesional_foto`).
 *
 * El portafolio importa más de lo que parece: la gente escoge barbero viendo
 * cortes, no leyendo precios.
 */
class ProfesionalFoto extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'profesional_foto';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }
}
