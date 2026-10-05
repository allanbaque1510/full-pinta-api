<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearImagenLocalRequest extends FormRequest
{
    // Autorización: middleware `permiso` en routes.php.

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'tipo' => ['required', Rule::exists('tipo_imagen', 'codigo')],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
