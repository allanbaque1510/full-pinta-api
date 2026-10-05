<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VincularCuentaProfesionalRequest extends FormRequest
{
    // Autorización: middleware `permiso` en routes.php.

    public function rules(): array
    {
        return [
            'telefono' => ['required', 'string', 'regex:/^09\d{8}$/'],
        ];
    }
}
