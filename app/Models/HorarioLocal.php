<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HorarioLocal (`horario_local`).
 *
 * Varias filas por día permiten partir jornada (mañana/tarde).
 */
class HorarioLocal extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'horario_local';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }
}
