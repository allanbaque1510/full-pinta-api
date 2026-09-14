<?php

namespace App\Modules\Reviews\Http\Requests;

use App\Models\Resena;
use Illuminate\Foundation\Http\FormRequest;

class CrearResenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crear', [Resena::class, $this->route('cita')]);
    }

    public function rules(): array
    {
        return [
            'puntaje_local' => ['required', 'integer', 'between:1,5'],
            'puntaje_profesional' => ['nullable', 'integer', 'between:1,5'],
            'puntualidad' => ['nullable', 'integer', 'between:1,5'],
            'limpieza' => ['nullable', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string'],
        ];
    }
}
