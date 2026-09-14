<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Público: es el primer paso de registro o inicio de sesión, antes de
        // que exista ningún usuario autenticado.
        return true;
    }

    public function rules(): array
    {
        return [
            // Celular ecuatoriano: 09 + 8 dígitos. Es el único formato que
            // recibe WhatsApp/SMS, que es para lo que existe este teléfono.
            'telefono' => ['required', 'string', 'regex:/^09\d{8}$/'],
        ];
    }
}
