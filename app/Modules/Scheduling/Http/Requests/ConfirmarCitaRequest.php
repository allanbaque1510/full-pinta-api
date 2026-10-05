<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Support\Auth\ContextoAcceso;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmarCitaRequest extends FormRequest
{
    /** Confirmar su propio hold, o el staff confirmándolo por él (walk-in tardío, por ejemplo). */
    public function authorize(): bool
    {
        $cita = $this->route('cita')->loadMissing('local');

        return $cita->cliente_id === $this->user()->id
            || (new ContextoAcceso($this->user()))->tienePermiso($cita->local, 'citas.confirmar');
    }

    public function rules(): array
    {
        return [];
    }
}
