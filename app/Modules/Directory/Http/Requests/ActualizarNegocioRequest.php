<?php

namespace App\Modules\Directory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarNegocioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('actualizar', $this->route('negocio'));
    }

    public function rules(): array
    {
        return [
            'nombre_marca' => ['sometimes', 'string', 'max:255'],
            'ruc' => ['sometimes', 'nullable', 'string', 'regex:/^\d{13}$/'],
        ];
    }
}
