<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SincronizarTamanosRequest extends FormRequest
{
    // Autorización: middleware `permiso` en routes.php.

    public function rules(): array
    {
        return [
            'tamanos' => ['present', 'array'],
            'tamanos.*.tamano' => [
                'required', 'distinct', Rule::exists('tamano_mascota', 'codigo')->where('activo', true),
            ],
            'tamanos.*.precio' => ['required', 'numeric', 'min:0'],
            'tamanos.*.duracion_min' => ['required', 'integer', 'min:1'],
        ];
    }
}
