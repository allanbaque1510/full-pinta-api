<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarServicioLocalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('servicio')->local);
    }

    public function rules(): array
    {
        return [
            'precio' => ['sometimes', 'numeric', 'min:0'],
            'precio_desde' => ['sometimes', 'boolean'],
            'duracion_min' => ['sometimes', 'integer', 'min:1'],
            'buffer_min' => ['sometimes', 'integer', 'min:0'],
            'comisionable' => ['sometimes', 'boolean'],
        ];
    }
}
