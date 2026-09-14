<?php

namespace App\Modules\Directory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearNegocioRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Cualquier usuario autenticado puede crear su propio negocio y
        // convertirse en su propietario.
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_marca' => ['required', 'string', 'max:255'],
            'ruc' => ['nullable', 'string', 'regex:/^\d{13}$/'],
        ];
    }
}
