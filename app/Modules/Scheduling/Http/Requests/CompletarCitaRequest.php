<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Support\Auth\ContextoAcceso;
use Illuminate\Foundation\Http\FormRequest;

class CompletarCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cita = $this->route('cita')->loadMissing('local');

        return $this->user()->profesional?->id === $cita->profesional_id
            || (new ContextoAcceso($this->user()))->tienePermiso($cita->local, 'citas.completar');
    }

    public function rules(): array
    {
        return [
            // 100% al profesional y NO es base de comisión (§4.7).
            'propina' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
