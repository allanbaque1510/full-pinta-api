<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearTurnoFechaRequest extends FormRequest
{
    // Autorización: el controller resuelve `local_id` del body y consulta
    // `ContextoAcceso::tienePermiso()` — depende del body, no de un
    // parámetro de ruta.

    public function rules(): array
    {
        return [
            'local_id' => ['required', 'uuid', Rule::exists('local', 'id')],
            'fecha' => ['required', 'date'],
            'tipo' => ['required', 'in:extra,reemplaza,cancela'],
            'entra' => ['nullable', 'date_format:H:i'],
            'sale' => ['nullable', 'date_format:H:i'],
            'nota' => ['nullable', 'string'],
        ];
    }
}
