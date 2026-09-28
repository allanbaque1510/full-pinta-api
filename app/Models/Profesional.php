<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Profesional (`profesional`).
 *
 * `usuario_id` es nullable a propósito: muchos barberos no van a instalar nada y
 * el local gestiona su agenda. El perfil existe igual.
 *
 * `traslado_min` NO lo puede validar un constraint — la base no sabe de
 * geografía. Va en el motor de disponibilidad (§5.1, filtro 6).
 */
class Profesional extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'profesional';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'perfil_publico' => 'boolean',
            'traslado_min' => 'integer',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function imagenes(): MorphMany
    {
        return $this->morphMany(Imagen::class, 'objeto');
    }

    public function fotoPerfil(): BelongsTo
    {
        return $this->belongsTo(Imagen::class, 'foto_perfil_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'profesional_id');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'profesional_id');
    }

    public function turnoFechas(): HasMany
    {
        return $this->hasMany(TurnoFecha::class, 'profesional_id');
    }

    public function habilidades(): HasMany
    {
        return $this->hasMany(Habilidad::class, 'profesional_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'profesional_id');
    }

    public function resenas(): HasMany
    {
        return $this->hasMany(Resena::class, 'profesional_id');
    }

    public function excepciones(): HasMany
    {
        return $this->hasMany(Excepcion::class, 'profesional_id');
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class, 'profesional_id');
    }

    public function tieneCuentaPropia(): bool
    {
        return $this->usuario_id !== null;
    }
}
