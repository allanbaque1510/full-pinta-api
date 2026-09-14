<?php

namespace App\Modules\Directory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuscarLocalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Público: es la puerta de entrada del cliente, antes de tener cuenta.
        return true;
    }

    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radio_m' => ['sometimes', 'integer', 'min:100', 'max:50000'],
            'vertical' => ['sometimes', 'string', Rule::exists('vertical', 'codigo')],
            'catalogo_servicio_id' => ['sometimes', 'uuid', Rule::exists('catalogo_servicio', 'id')],
            'precio_min' => ['sometimes', 'numeric', 'min:0'],
            'precio_max' => ['sometimes', 'numeric', 'min:0'],
            'amenidades' => ['sometimes', 'array'],
            'amenidades.*' => ['string', Rule::exists('amenidad', 'codigo')],
            'disponible' => ['sometimes', 'boolean'],
            'fecha' => ['sometimes', 'date'],
            'abierto_ahora' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
