<?php

namespace App\Modules\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SincronizarPreferenciasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preferencias' => ['required', 'array', 'min:1'],
            'preferencias.*.categoria' => ['required', Rule::exists('notificacion_categoria', 'codigo')],
            'preferencias.*.push' => ['required', 'boolean'],
            'preferencias.*.whatsapp' => ['required', 'boolean'],
        ];
    }
}
