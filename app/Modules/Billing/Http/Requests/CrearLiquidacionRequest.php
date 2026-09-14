<?php

namespace App\Modules\Billing\Http\Requests;

use App\Models\Liquidacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearLiquidacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionar', [Liquidacion::class, $this->route('local')]);
    }

    public function rules(): array
    {
        return [
            'profesional_id' => ['required', 'uuid', Rule::exists('profesional', 'id')],
            'periodo_desde' => ['required', 'date'],
            'periodo_hasta' => ['required', 'date', 'after_or_equal:periodo_desde'],
        ];
    }
}
