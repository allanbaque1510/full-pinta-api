<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearHabilidadRequest extends FormRequest
{
    // Autorización: el controller resuelve `servicio_local_id` → `local` y
    // consulta `ContextoAcceso::tienePermiso()` — depende del body, no de
    // un parámetro de ruta.

    public function rules(): array
    {
        return [
            'servicio_local_id' => ['required', 'uuid', Rule::exists('servicio_local', 'id')],
            'precio_override' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
