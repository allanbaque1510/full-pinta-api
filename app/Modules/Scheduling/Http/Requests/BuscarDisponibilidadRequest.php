<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuscarDisponibilidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Público: el cliente necesita ver horarios antes de tener cuenta.
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*' => ['uuid', Rule::exists('servicio_local', 'id')->where('activo', true)],
            'profesional_id' => ['nullable', 'uuid', Rule::exists('profesional', 'id')],
        ];
    }
}
