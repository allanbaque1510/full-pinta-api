<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AlternarFavoritoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'local_id' => ['required_without:profesional_id', 'prohibits:profesional_id', 'uuid', Rule::exists('local', 'id')],
            'profesional_id' => ['required_without:local_id', 'uuid', Rule::exists('profesional', 'id')],
        ];
    }
}
