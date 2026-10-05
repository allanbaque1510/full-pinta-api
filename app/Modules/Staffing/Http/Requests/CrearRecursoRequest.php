<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearRecursoRequest extends FormRequest
{
    // Autorización: middleware `permiso` en routes.php.

    public function rules(): array
    {
        return [
            // `ninguno` es solo para servicios del catálogo: un recurso físico
            // siempre es algo concreto.
            'tipo' => [
                'required', Rule::exists('tipo_recurso', 'codigo')->where('activo', true)->whereNot('codigo', 'ninguno'),
            ],
            'nombre' => ['required', 'string', 'max:60'],
        ];
    }
}
