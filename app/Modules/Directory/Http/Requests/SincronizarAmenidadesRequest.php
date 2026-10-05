<?php

namespace App\Modules\Directory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SincronizarAmenidadesRequest extends FormRequest
{
    // Autorización: middleware `permiso` en routes.php.

    public function rules(): array
    {
        return [
            'amenidades' => ['present', 'array'],
            'amenidades.*.amenidad_id' => [
                'required',
                'uuid',
                Rule::exists('amenidad', 'id')->where('activo', true),
            ],
            'amenidades.*.detalle' => ['nullable', 'string', 'max:255'],
        ];
    }
}
