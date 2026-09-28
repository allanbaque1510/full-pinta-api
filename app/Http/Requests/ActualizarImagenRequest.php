<?php

namespace App\Http\Requests;

use App\Models\Local;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarImagenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dueno = $this->route('imagen')->objeto;

        return $dueno instanceof Local
            ? $this->user()->can('gestionarCatalogo', $dueno)
            : $this->user()->can('actualizar', $dueno);
    }

    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'url', 'max:2048'],
            'tipo' => ['sometimes', Rule::exists('tipo_imagen', 'codigo')],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
