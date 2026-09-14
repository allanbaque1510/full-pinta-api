<?php

namespace App\Models;

use App\Casts\Ubicacion;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Local (`local`).
 *
 * `ubicacion` es `geography(Point,4326)`: se lee y escribe como
 * `['lat' => float, 'lng' => float]` vía el cast `App\Casts\Ubicacion` — ver
 * ese archivo para el porqué. `score_ranking` lo calcula un job nocturno,
 * nunca un request (§12.5).
 */
class Local extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'local';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ubicacion' => Ubicacion::class,
            'verificado' => 'boolean',
            'verificado_at' => 'immutable_datetime',
            'lead_time_min' => 'integer',
            'horizonte_dias' => 'integer',
            'politica_cancelacion_horas' => 'integer',
            'score_ranking' => 'decimal:4',
        ];
    }

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class, 'negocio_id');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioLocal::class, 'local_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(LocalFoto::class, 'local_id');
    }

    public function miembros(): HasMany
    {
        return $this->hasMany(NegocioMiembro::class, 'local_id');
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(ServicioLocal::class, 'local_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'local_id');
    }

    public function recursos(): HasMany
    {
        return $this->hasMany(Recurso::class, 'local_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'local_id');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'local_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'local_id');
    }

    public function esperas(): HasMany
    {
        return $this->hasMany(Espera::class, 'local_id');
    }

    public function resenas(): HasMany
    {
        return $this->hasMany(Resena::class, 'local_id');
    }

    public function excepciones(): HasMany
    {
        return $this->hasMany(Excepcion::class, 'local_id');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudCatalogo::class, 'local_id');
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class, 'local_id');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(ClienteLocal::class, 'local_id');
    }

    public function disponibilidadDias(): HasMany
    {
        return $this->hasMany(DisponibilidadDia::class, 'local_id');
    }

    public function amenidades(): BelongsToMany
    {
        return $this->belongsToMany(Amenidad::class, 'local_amenidad', 'local_id', 'amenidad_id')
            ->withPivot('detalle');
    }

    public function estaOperativo(): bool
    {
        return $this->estado === 'activo';
    }
}
