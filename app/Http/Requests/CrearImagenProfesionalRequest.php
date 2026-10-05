<?php

namespace App\Http\Requests;

use App\Support\Auth\ContextoAcceso;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sin `tipo`: el portafolio del profesional es siempre `'muestra'`, forzado
 * por el controller — antes tampoco lo pedía (`profesional_foto` no tenía
 * columna `tipo`).
 */
class CrearImagenProfesionalRequest extends FormRequest
{
    /** Quien administra alguno de sus locales, o el propio profesional. */
    public function authorize(): bool
    {
        $profesional = $this->route('profesional');
        $contexto = new ContextoAcceso($this->user());

        return $contexto->tienePermisoSobreProfesional($profesional, 'profesionales.imagenes.store')
            || $this->user()->profesional?->id === $profesional->id;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
