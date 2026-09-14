<?php

namespace App\Modules\Reviews\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResponderResenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('responder', $this->route('resena'));
    }

    public function rules(): array
    {
        return [
            'respuesta_local' => ['required', 'string'],
        ];
    }
}
