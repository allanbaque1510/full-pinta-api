<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarRecuperacionContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Público: es el punto de entrada de quien perdió el acceso, antes
        // de tener sesión.
        return true;
    }

    public function rules(): array
    {
        return [
            'canal' => ['required', 'in:whatsapp,email'],
            'telefono' => ['required_if:canal,whatsapp', 'string', 'regex:/^09\d{8}$/'],
            'email' => ['required_if:canal,email', 'string', 'email'],
        ];
    }
}
