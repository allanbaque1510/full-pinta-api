<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerificarOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'telefono' => ['required', 'string', 'regex:/^09\d{8}$/'],
            'codigo' => ['required', 'string', 'regex:/^\d{6}$/'],
            // Solo hace falta si el teléfono es nuevo; VerificarOtp lo exige
            // en ese caso puntual y lo dice con su propio error.
            'nombre' => ['nullable', 'string', 'max:255'],
        ];
    }
}
