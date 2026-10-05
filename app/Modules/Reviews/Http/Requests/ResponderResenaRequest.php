<?php

namespace App\Modules\Reviews\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResponderResenaRequest extends FormRequest
{
    // Autorización: `can:responder,resena` en routes.php.

    public function rules(): array
    {
        return [
            'respuesta_local' => ['required', 'string'],
        ];
    }
}
