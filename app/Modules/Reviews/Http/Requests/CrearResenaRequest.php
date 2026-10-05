<?php

namespace App\Modules\Reviews\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearResenaRequest extends FormRequest
{
    /** Propiedad puntual: solo el cliente dueño de la cita puede reseñarla — nunca el staff (§3.2). */
    public function authorize(): bool
    {
        return $this->route('cita')->cliente_id === $this->user()->id;
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
