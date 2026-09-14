<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarcarNoShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionar', $this->route('cita'));
    }

    public function rules(): array
    {
        return [];
    }
}
