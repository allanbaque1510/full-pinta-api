<?php

namespace App\Modules\Directory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearLocalFotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('local'));
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'tipo' => ['required', 'in:fachada,interior,trabajo'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
