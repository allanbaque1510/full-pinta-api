<?php

namespace App\Modules\Directory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearHorarioRequest extends FormRequest
{
    // Autorización: middleware `permiso` en routes.php.

    public function rules(): array
    {
        return [
            'dia_semana' => ['required', 'integer', 'between:0,6'],
            'abre' => ['required', 'date_format:H:i'],
            'cierra' => ['required', 'date_format:H:i'],
        ];
    }
}
