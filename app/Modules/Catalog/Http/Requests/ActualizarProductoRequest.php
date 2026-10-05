<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarProductoRequest extends FormRequest
{
    // Autorización: middleware `permiso` en routes.php.

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:255'],
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'precio' => ['sometimes', 'numeric', 'min:0'],
            'comision_pct' => ['sometimes', 'numeric', 'between:0,100'],
            'foto_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
