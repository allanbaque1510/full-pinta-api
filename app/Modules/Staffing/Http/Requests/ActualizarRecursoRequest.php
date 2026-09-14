<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarRecursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('recurso')->local);
    }

    public function rules(): array
    {
        return [
            'tipo' => [
                'sometimes', Rule::exists('tipo_recurso', 'codigo')->where('activo', true)->whereNot('codigo', 'ninguno'),
            ],
            'nombre' => ['sometimes', 'string', 'max:60'],
        ];
    }
}
