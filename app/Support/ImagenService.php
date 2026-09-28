<?php

namespace App\Support;

use App\Events\ImagenModificada;
use App\Models\Imagen;
use App\Models\TipoImagen;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Galería polimórfica compartida (§4.4): `local`, `profesional`, `mascota`,
 * `usuario`... comparten la misma tabla `imagen` en vez de una por dueño.
 * Vive en `Support`, no en un módulo — ningún módulo es dueño de este dato,
 * lo consumen Directory y Staffing por igual.
 */
final readonly class ImagenService
{
    public function listar(string $objetoType, string $objetoId): Collection
    {
        return Imagen::with('tipo')
            ->where('objeto_type', $objetoType)
            ->where('objeto_id', $objetoId)
            ->orderBy('orden')
            ->get();
    }

    /**
     * @param  array{url: string, tipo: string, orden?: int}  $datos  `tipo` es el código de `tipo_imagen`, no su id.
     */
    public function agregar(string $objetoType, string $objetoId, array $datos): Imagen
    {
        $imagen = Imagen::create([
            'objeto_type' => $objetoType,
            'objeto_id' => $objetoId,
            'tipo_id' => TipoImagen::where('codigo', $datos['tipo'])->value('id'),
            'url' => $datos['url'],
            'orden' => $datos['orden'] ?? 0,
        ]);

        ImagenModificada::dispatch($objetoType, $objetoId);

        return $imagen->load('tipo');
    }

    /**
     * @param  array{url?: string, tipo?: string, orden?: int}  $datos
     */
    public function actualizar(Imagen $imagen, array $datos): Imagen
    {
        if (isset($datos['tipo'])) {
            $datos['tipo_id'] = TipoImagen::where('codigo', $datos['tipo'])->value('id');
            unset($datos['tipo']);
        }

        $imagen->update($datos);

        ImagenModificada::dispatch($imagen->objeto_type, $imagen->objeto_id);

        return $imagen->load('tipo');
    }

    public function eliminar(Imagen $imagen): void
    {
        [$objetoType, $objetoId] = [$imagen->objeto_type, $imagen->objeto_id];

        $imagen->delete();

        ImagenModificada::dispatch($objetoType, $objetoId);
    }

    /**
     * Sube la foto de perfil de un dueño (`usuario`, `profesional`, `mascota`,
     * `producto`...) y apunta su puntero en la misma transacción — la FK no
     * garantiza que la imagen referenciada sea del mismo dueño, así que este
     * es el único camino que debe escribirla. `$url = null` la quita (solo el
     * puntero: la fila de `imagen` vieja no se borra). `$columna` es el
     * nombre real de la FK en la tabla del dueño — no todas se llaman
     * `foto_perfil_id` (`producto.foto_id`, por ejemplo).
     */
    public function establecerFotoPerfil(Model $dueno, string $objetoType, ?string $url, string $columna = 'foto_perfil_id'): void
    {
        if ($url === null) {
            $dueno->update([$columna => null]);
            ImagenModificada::dispatch($objetoType, $dueno->id);

            return;
        }

        DB::transaction(function () use ($dueno, $objetoType, $url, $columna) {
            $imagen = Imagen::create([
                'objeto_type' => $objetoType,
                'objeto_id' => $dueno->id,
                'tipo_id' => $this->tipoPerfilId(),
                'url' => $url,
            ]);

            $dueno->update([$columna => $imagen->id]);
        });

        ImagenModificada::dispatch($objetoType, $dueno->id);
    }

    /**
     * Autocuración si `TipoImagenSeeder` no corrió — mismo criterio que
     * `NotificacionCategoria` en `NotificacionService` y el plan `free` en
     * `SuscripcionService::cancelar()`: conjunto cerrado y fijo (§4.4), no
     * vale la pena que este camino falle por un seeder que no se ejecutó.
     */
    private function tipoPerfilId(): string
    {
        return TipoImagen::where('codigo', 'perfil')->value('id')
            ?? TipoImagen::create(['codigo' => 'perfil', 'nombre' => 'Foto de perfil', 'activo' => true])->id;
    }
}
