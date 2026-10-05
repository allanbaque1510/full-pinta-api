<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarTurnoRequest extends FormRequest
{
    // Autorización: middleware `permiso` en routes.php.

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
