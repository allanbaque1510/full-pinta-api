<?php

namespace App\Modules\Reviews\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Cualquier usuario autenticado puede reportar contenido — no hay
        // noción de "dueño" aquí.
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(['resena', 'foto', 'local', 'profesional'])],
            // Polimórfico sin FK a propósito (§4.8): no se valida que exista,
            // solo el formato.
            'objeto_id' => ['required', 'uuid'],
            'motivo' => ['required', Rule::in(['difamacion', 'contenido_inapropiado', 'falso', 'spam', 'otro'])],
            'detalle' => ['nullable', 'string'],
        ];
    }
}
