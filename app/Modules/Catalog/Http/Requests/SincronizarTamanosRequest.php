<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SincronizarTamanosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('servicio')->local);
    }

    public function rules(): array
    {
        return [
            'tamanos' => ['present', 'array'],
            'tamanos.*.tamano' => [
                'required', 'distinct', Rule::exists('tamano_mascota', 'codigo')->where('activo', true),
            ],
            'tamanos.*.precio' => ['required', 'numeric', 'min:0'],
            'tamanos.*.duracion_min' => ['required', 'integer', 'min:1'],
        ];
    }
}
