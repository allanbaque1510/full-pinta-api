<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearEsperaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Cualquier cliente autenticado puede anotarse en la lista de espera.
        return true;
    }

    public function rules(): array
    {
        return [
            'servicio_local_id' => ['required', 'uuid', Rule::exists('servicio_local', 'id')->where('activo', true)],
            'fecha_deseada' => ['required', 'date'],
            'profesional_id' => ['nullable', 'uuid', Rule::exists('profesional', 'id')],
            'desde' => ['nullable', 'date_format:H:i'],
            'hasta' => ['nullable', 'date_format:H:i', 'after:desde'],
        ];
    }
}
