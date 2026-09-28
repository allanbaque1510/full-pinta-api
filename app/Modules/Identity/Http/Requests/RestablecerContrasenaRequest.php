<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RestablecerContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'canal' => ['required', 'in:whatsapp,email'],
            'telefono' => ['required_if:canal,whatsapp', 'string', 'regex:/^09\d{8}$/'],
            'email' => ['required_if:canal,email', 'string', 'email'],
            'codigo' => ['required', 'string', 'regex:/^\d{6}$/'],
            'password' => ['required', 'string', Password::min(8)],
        ];
    }
}
