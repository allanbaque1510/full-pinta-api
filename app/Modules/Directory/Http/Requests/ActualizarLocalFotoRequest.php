<?php

namespace App\Modules\Directory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarLocalFotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('foto')->local);
    }

    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'url', 'max:2048'],
            'tipo' => ['sometimes', 'in:fachada,interior,trabajo'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
