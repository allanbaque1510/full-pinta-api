<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearServicioLocalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('local'));
    }

    public function rules(): array
    {
        return [
            'catalogo_servicio_id' => ['required', 'uuid', Rule::exists('catalogo_servicio', 'id')->where('activo', true)],
            'precio' => ['required', 'numeric', 'min:0'],
            'precio_desde' => ['sometimes', 'boolean'],
            'duracion_min' => ['required', 'integer', 'min:1'],
            'buffer_min' => ['sometimes', 'integer', 'min:0'],
            'comisionable' => ['sometimes', 'boolean'],
        ];
    }
}
