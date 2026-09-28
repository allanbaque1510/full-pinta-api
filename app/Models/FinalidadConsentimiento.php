<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FinalidadConsentimiento (`finalidad_consentimiento`): operacion_servicio,
 * comunicaciones_transaccionales, marketing, transferencia_internacional.
 *
 * Tabla de parámetros (§13.1) — antes `consentimiento.finalidad` era un
 * varchar+CHECK sin texto propio; el nombre/descripción que ve el usuario
 * en la pantalla de consentimiento vive aquí, no hardcodeado en el Flutter.
 */
class FinalidadConsentimiento extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'finalidad_consentimiento';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'obligatorio' => 'boolean',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function consentimientos(): HasMany
    {
        return $this->hasMany(Consentimiento::class, 'finalidad_id');
    }

    /**
     * El documento legal vigente que respalda esta finalidad, si tiene uno
     * asignado (`documento_tipo`) y ese tipo ya tiene una versión publicada.
     * Nulo en ambos casos es válido — ver `documento_tipo` en la migración.
     */
    public function documentoVigente(): ?DocumentoLegal
    {
        if ($this->documento_tipo === null) {
            return null;
        }

        return DocumentoLegal::vigente($this->documento_tipo);
    }
}
