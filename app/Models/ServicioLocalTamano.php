<?php

namespace App\Models;

use App\Models\Concerns\ClaveCompuesta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ServicioLocalTamano (`servicio_local_tamano`).
 *
 * Obligatorio para grooming: el precio y la duración dependen del animal. Bañar
 * un yorkshire no cuesta lo mismo que un golden (§4.5).
 */
class ServicioLocalTamano extends Model
{
    use ClaveCompuesta, HasFactory;

    protected $table = 'servicio_local_tamano';

    protected $guarded = ['id'];

    /** @var array<int,string> */
    protected $clavePrimaria = ['servicio_local_id', 'tamano_id'];

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'duracion_min' => 'integer',
        ];
    }

    public function servicioLocal(): BelongsTo
    {
        return $this->belongsTo(ServicioLocal::class, 'servicio_local_id');
    }

    public function tamano(): BelongsTo
    {
        return $this->belongsTo(TamanoMascota::class, 'tamano_id');
    }
}
