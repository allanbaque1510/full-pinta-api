<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearTurnoRequest extends FormRequest
{
    // Autorización: middleware `permiso` en routes.php.

    public function rules(): array
    {
        return [
            'dia_semana' => ['required', 'integer', 'between:0,6'],
            'entra' => ['required', 'date_format:H:i'],
            'sale' => ['required', 'date_format:H:i'],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after:vigente_desde'],
        ];
    }
}
