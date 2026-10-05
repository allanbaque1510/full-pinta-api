<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarRecursoRequest extends FormRequest
{
    // Autorización: middleware `permiso` en routes.php.

    public function rules(): array
    {
        return [
            'tipo' => [
                'sometimes', Rule::exists('tipo_recurso', 'codigo')->where('activo', true)->whereNot('codigo', 'ninguno'),
            ],
            'nombre' => ['sometimes', 'string', 'max:60'],
        ];
    }
}
