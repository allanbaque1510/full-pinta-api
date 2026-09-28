<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sin `tipo`: el portafolio del profesional es siempre `'muestra'`, forzado
 * por el controller — antes tampoco lo pedía (`profesional_foto` no tenía
 * columna `tipo`).
 */
class CrearImagenProfesionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('actualizar', $this->route('profesional'));
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
