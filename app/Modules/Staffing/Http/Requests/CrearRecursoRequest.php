<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearRecursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('local'));
    }

    public function rules(): array
    {
        return [
            // `ninguno` es solo para servicios del catálogo: un recurso físico
            // siempre es algo concreto.
            'tipo' => [
                'required', Rule::exists('tipo_recurso', 'codigo')->where('activo', true)->whereNot('codigo', 'ninguno'),
            ],
            'nombre' => ['required', 'string', 'max:60'],
        ];
    }
}
