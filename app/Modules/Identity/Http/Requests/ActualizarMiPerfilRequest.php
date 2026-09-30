<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarMiPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:255'],
            'genero' => ['sometimes', 'nullable', 'in:m,f,otro,no_decir'],
            'fecha_nacimiento' => ['sometimes', 'nullable', 'date', 'before:today'],
            'foto_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
