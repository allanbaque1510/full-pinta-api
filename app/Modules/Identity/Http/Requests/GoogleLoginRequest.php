<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GoogleLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            // Mismo formato que el resto de la app: celular ecuatoriano.
            // Se ignora si la cuenta resuelta ya tiene teléfono propio (ver
            // `IniciarSesionConGoogle`), pero igual tiene que venir con forma
            // válida por si hace falta crear la cuenta.
            'telefono' => ['required', 'string', 'regex:/^09\d{8}$/'],
        ];
    }
}
