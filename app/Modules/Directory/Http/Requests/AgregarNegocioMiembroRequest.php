<?php

namespace App\Modules\Directory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgregarNegocioMiembroRequest extends FormRequest
{
    // Autorización: middleware `permiso` en routes.php.

    public function rules(): array
    {
        return [
            'telefono' => ['required', 'string', 'regex:/^09\d{8}$/'],
            'rol' => ['required', 'in:admin,recepcion'],
            'local_id' => [
                'nullable', 'uuid',
                Rule::exists('local', 'id')->where('negocio_id', $this->route('negocio')->id),
            ],
        ];
    }
}
