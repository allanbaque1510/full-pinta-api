<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Da a cada acción de controlador la forma: un try que llama a un único
 * servicio, y dos catch — `ValidationException` y `Throwable`.
 *
 * Toda la lógica vive en el servicio (el caso de uso en `Application/`); el
 * controlador solo traduce su resultado o su excepción a JSON, usando los
 * helpers de `app/Support/helpers.php` (`response_success`,
 * `response_validacion`, `response_error`) para que la forma de la respuesta
 * no se repita a mano en cada rama. Esto vive en un trait, no repetido en
 * cada método, para no copiar el mismo bloque de 15 líneas en cada acción de
 * cada controlador — la FORMA que pide el proyecto es la misma, factorizada
 * una sola vez.
 *
 * ## Por qué el catch(Throwable) no es un 500 a secas
 *
 * Si un servicio lanza una de las excepciones de dominio propias (ver
 * `Application/Exceptions/*` de cada módulo — `CodigoOtpIncorrecto`,
 * `DemasiadasSolicitudesOtp`), esa excepción ya sabe renderizarse a sí misma
 * (`render()`) con el código HTTP y el payload correctos (422, 429 con
 * `Retry-After`, etc.). El catch(Throwable) delega ahí primero. También deja
 * pasar `AuthorizationException` (403) y `ModelNotFoundException` (404) con
 * su significado real, en vez de aplastarlas contra un 500 genérico. Solo lo
 * que de verdad no se esperaba cae al 500 final.
 */
trait EjecutaServicio
{
    protected function ejecutar(callable $servicio, int $statusExito = 200): JsonResponse
    {
        try {
            $resultado = $servicio();

            return $resultado instanceof JsonResponse
                ? $resultado
                : response_success($resultado, $statusExito);
        } catch (ValidationException $e) {
            // Formato estándar de Laravel (`message`/`errors`), no traducido:
            // es el mismo shape que ya produce cualquier FormRequest fuera de
            // este trait, y lo esperan `assertJsonValidationErrors()` y
            // cualquier cliente HTTP genérico de Laravel.
            return response_validacion($e->getMessage(), $e->errors(), $e->status);
        } catch (Throwable $e) {
            return $this->traducirError($e);
        }
    }

    private function traducirError(Throwable $e): JsonResponse
    {
        // Las excepciones de dominio del proyecto (OTP, transiciones de
        // estado, etc.) ya definen su propio render(). Se usa tal cual.
        if (method_exists($e, 'render')) {
            $respuesta = $e->render(request());

            if ($respuesta instanceof JsonResponse) {
                return $respuesta;
            }
        }

        if ($e instanceof AuthorizationException) {
            return response_error(403, 'no_autorizado', $e->getMessage() ?: 'No tienes permiso para esto.');
        }

        if ($e instanceof ModelNotFoundException) {
            return response_error(404, 'no_encontrado', 'El recurso no existe.');
        }

        report($e);

        return response_error(
            500,
            'error_interno',
            config('app.debug') ? $e->getMessage() : 'Ocurrió un error inesperado.',
        );
    }
}
