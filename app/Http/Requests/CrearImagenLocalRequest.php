<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearImagenLocalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('local'));
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'tipo' => ['required', Rule::exists('tipo_imagen', 'codigo')],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
