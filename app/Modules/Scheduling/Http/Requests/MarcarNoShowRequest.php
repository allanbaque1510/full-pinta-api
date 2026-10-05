<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Support\Auth\ContextoAcceso;
use Illuminate\Foundation\Http\FormRequest;

class MarcarNoShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cita = $this->route('cita')->loadMissing('local');

        return $this->user()->profesional?->id === $cita->profesional_id
            || (new ContextoAcceso($this->user()))->tienePermiso($cita->local, 'citas.no-show');
    }

    public function rules(): array
    {
        return [];
    }
}
