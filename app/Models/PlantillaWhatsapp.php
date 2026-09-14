<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PlantillaWhatsapp (`plantilla_whatsapp`).
 *
 * Las aprueba Meta, no nosotros. El trámite toma días y el texto no se puede
 * cambiar sin volver a aprobar: bloquea el lanzamiento si se deja al final
 * (§11.9).
 */
class PlantillaWhatsapp extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'plantilla_whatsapp';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
        ];
    }

    public function estaAprobada(): bool
    {
        return $this->estado === 'aprobada';
    }
}
