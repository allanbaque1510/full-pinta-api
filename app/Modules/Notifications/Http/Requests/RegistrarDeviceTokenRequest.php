<?php

namespace App\Modules\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'plataforma' => ['required', Rule::in(['android', 'ios'])],
            'apns_token' => ['nullable', 'string'],
            'app_version' => ['nullable', 'string'],
        ];
    }
}
