<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegistrarConEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:usuario,email'],
            'password' => ['required', 'string', Password::min(8)],
            'telefono' => ['required', 'string', 'regex:/^09\d{8}$/', 'unique:usuario,telefono'],
        ];
    }
}
