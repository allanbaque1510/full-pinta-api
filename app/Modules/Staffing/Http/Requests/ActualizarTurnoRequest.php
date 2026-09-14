<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('turno')->local);
    }

    public function rules(): array
    {
        return [
            'dia_semana' => ['sometimes', 'integer', 'between:0,6'],
            'entra' => ['sometimes', 'date_format:H:i'],
            'sale' => ['sometimes', 'date_format:H:i'],
            'vigente_desde' => ['sometimes', 'date'],
            'vigente_hasta' => ['sometimes', 'nullable', 'date', 'after:vigente_desde'],
        ];
    }
}
