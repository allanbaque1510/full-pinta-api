<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotencia de escrituras (§12.4).
 *
 * En móvil la red se cae a mitad del POST, el usuario vuelve a tocar "Agendar"
 * y se crean dos citas. Pasa siempre. El cliente manda un `Idempotency-Key`
 * (UUID que él genera); si la clave repite, se devuelve la respuesta guardada
 * sin ejecutar nada.
 *
 * La clave se *reclama* con un INSERT antes de ejecutar: eso resuelve también
 * el caso de dos peticiones simultáneas con la misma clave, que es justo lo que
 * pasa cuando el usuario toca dos veces seguidas.
 */
class Idempotencia
{
    /** SQLSTATE de violación de unicidad. */
    private const UNIQUE_VIOLATION = '23505';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->esEscritura($request)) {
            return $next($request);
        }

        $clave = $request->header('Idempotency-Key');

        if (blank($clave)) {
            return $this->error(400, 'idempotency_key_requerida',
                'Esta operación requiere el header Idempotency-Key.');
        }

        if (! preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $clave)) {
            return $this->error(400, 'idempotency_key_invalida',
                'El header Idempotency-Key no tiene un formato válido.');
        }

        $usuarioId = $request->user()?->getAuthIdentifier();

        // Reclamar la clave. Si ya existe, otra petición igual llegó antes.
        try {
            DB::table('idempotencia')->insert([
                'id' => (string) Str::uuid(),
                'clave' => $clave,
                'usuario_id' => $usuarioId,
                'endpoint' => $this->endpoint($request),
                'status' => null,
                'respuesta' => null,
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() !== self::UNIQUE_VIOLATION) {
                throw $e;
            }

            return $this->reproducir($clave, $usuarioId, $request);
        }

        try {
            $respuesta = $next($request);
        } catch (\Throwable $e) {
            // Si la ejecución falló, la clave se libera: el cliente debe poder
            // reintentar. Guardar el fallo convertiría un error transitorio en
            // permanente.
            DB::table('idempotencia')->where('clave', $clave)->delete();

            throw $e;
        }

        $this->guardar($clave, $respuesta);

        return $respuesta;
    }

    /**
     * Devuelve la respuesta ya guardada para una clave repetida.
     */
    private function reproducir(string $clave, ?string $usuarioId, Request $request): Response
    {
        $fila = DB::table('idempotencia')->where('clave', $clave)->first();

        if ($fila === null) {
            // La petición original falló y liberó la clave entre el INSERT y
            // esta lectura. Que el cliente reintente.
            return $this->error(409, 'idempotency_key_en_conflicto',
                'Esa clave está siendo procesada. Reintenta en unos segundos.');
        }

        // Una clave pertenece a quien la creó. Reusar la de otro usuario
        // filtraría su respuesta.
        if ($fila->usuario_id !== $usuarioId || $fila->endpoint !== $this->endpoint($request)) {
            return $this->error(422, 'idempotency_key_reutilizada',
                'Esa clave ya se usó para otra operación.');
        }

        if ($fila->status === null) {
            return $this->error(409, 'idempotency_key_en_curso',
                'Esa operación aún se está procesando.');
        }

        return new JsonResponse(
            json_decode($fila->respuesta, true),
            $fila->status,
            ['Idempotency-Replayed' => 'true'],
        );
    }

    private function guardar(string $clave, Response $respuesta): void
    {
        // Solo se guardan respuestas exitosas. Un 422 de validación debe poder
        // reintentarse con los datos corregidos y la misma clave.
        if ($respuesta->getStatusCode() >= 300) {
            DB::table('idempotencia')->where('clave', $clave)->delete();

            return;
        }

        DB::table('idempotencia')->where('clave', $clave)->update([
            'status' => $respuesta->getStatusCode(),
            'respuesta' => $respuesta->getContent(),
        ]);
    }

    private function esEscritura(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function endpoint(Request $request): string
    {
        return substr($request->method().' '.($request->route()?->uri() ?? $request->path()), 0, 120);
    }

    private function error(int $status, string $codigo, string $mensaje): JsonResponse
    {
        return new JsonResponse(['codigo' => $codigo, 'mensaje' => $mensaje], $status);
    }
}
