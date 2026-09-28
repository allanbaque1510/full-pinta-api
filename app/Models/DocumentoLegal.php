<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DocumentoLegal (`documento_legal`): política de privacidad, política de
 * marketing, términos y condiciones — versionados (§13.1).
 *
 * Inmutable una vez publicado, mismo criterio que `consentimiento`: una
 * versión nueva es una fila nueva, nunca se edita una ya publicada.
 */
class DocumentoLegal extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'documento_legal';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'vigente_desde' => 'immutable_datetime',
            'vigente_hasta' => 'immutable_datetime',
        ];
    }

    public function consentimientos(): HasMany
    {
        return $this->hasMany(Consentimiento::class, 'documento_legal_id');
    }

    /**
     * La versión vigente de un tipo de documento ahora mismo, o null si
     * todavía no se ha publicado ninguna (caso normal hasta que legal
     * confirme el contenido — ver `plan-revision-bd.md`).
     */
    public static function vigente(string $tipo): ?self
    {
        return self::where('tipo', $tipo)
            ->where('vigente_desde', '<=', now())
            ->where(fn ($query) => $query->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', now()))
            ->latest('vigente_desde')
            ->first();
    }
}
