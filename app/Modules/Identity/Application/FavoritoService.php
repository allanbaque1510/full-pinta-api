<?php

namespace App\Modules\Identity\Application;

use App\Models\Favorito;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;

/**
 * Favoritos (§7): un local o un profesional, nunca ambos (mismo CHECK de la
 * tabla, §4.3). Dos operaciones nada más, así que van juntas en un solo
 * servicio en vez de una clase por acción — el resto del proyecto (Directory,
 * Staffing, Scheduling) ya agrupa así lo que gira alrededor de un solo modelo.
 */
final readonly class FavoritoService
{
    public function listar(Usuario $usuario): Collection
    {
        return $usuario->favoritos()
            ->with([
                'local.servicios.catalogoServicio', 'local.amenidades', 'local.fotos',
                'local.horarios', 'local.resenas',
                'profesional.fotos', 'profesional.resenas', 'profesional.habilidades.servicioLocal.catalogoServicio',
            ])
            ->get();
    }

    /**
     * Un solo endpoint hace de alta y baja: si ya existe, lo quita; si no
     * existe, lo crea. `$localId` y `$profesionalId` son mutuamente
     * excluyentes, igual que la columna en base.
     *
     * @return array{agregado: bool, favorito: ?Favorito}
     */
    public function alternar(Usuario $usuario, ?string $localId, ?string $profesionalId): array
    {
        $existente = Favorito::where('usuario_id', $usuario->id)
            ->where('local_id', $localId)
            ->where('profesional_id', $profesionalId)
            ->first();

        if ($existente !== null) {
            $existente->delete();

            return ['agregado' => false, 'favorito' => null];
        }

        $favorito = Favorito::create([
            'usuario_id' => $usuario->id,
            'local_id' => $localId,
            'profesional_id' => $profesionalId,
        ]);

        return ['agregado' => true, 'favorito' => $favorito->load(['local', 'profesional'])];
    }
}
