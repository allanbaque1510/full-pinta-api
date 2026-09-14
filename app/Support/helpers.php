<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

if (! function_exists('throw_validacion')) {
    /**
     * Lanza una `ValidationException` real de Laravel en una sola línea, para
     * cuando un servicio necesita rechazar algo que el `FormRequest` no puede
     * expresar (una regla de negocio, no de formato) — ver skill `endpoint`.
     *
     * A propósito construye una `ValidationException` DE VERDAD (vía
     * `withMessages()`), no una excepción a mano con un mensaje suelto: así
     * sigue funcionando `assertJsonValidationErrors()` en los tests y el
     * `catch (ValidationException $e)` de `EjecutaServicio`, que llama a
     * `$e->errors()` esperando el `MessageBag` real que solo `withMessages()`
     * arma correctamente.
     *
     * ```php
     * throw_validacion('El RUC ya está registrado.');                 // campo genérico 'error'
     * throw_validacion('El RUC ya está registrado.', 'ruc');          // un campo concreto
     * throw_validacion(['estado' => "No se puede pausar un local 'borrador'."]);
     * throw_validacion(['ruc' => ['Ya existe.', 'Verifica el dígito verificador.']]);
     * ```
     *
     * @param  string|array<string,string|array<int,string>>  $mensaje  Un texto suelto, o un mapa campo => mensaje(s).
     * @param  string  $campo  Solo se usa cuando `$mensaje` es un string suelto.
     */
    function throw_validacion(string|array $mensaje, string $campo = 'error'): never
    {
        $errores = is_string($mensaje) ? [$campo => [$mensaje]] : $mensaje;

        $errores = array_map(
            fn (string|array $m): array => is_array($m) ? $m : [$m],
            $errores,
        );

        throw ValidationException::withMessages($errores);
    }
}

if (! function_exists('response_success')) {
    /**
     * Una respuesta exitosa: el recurso (o array) tal cual, sin envolver en
     * `{data: ...}` — `JsonResource::withoutWrapping()` ya está activo
     * globalmente (`AppServiceProvider`), este helper solo evita repetir
     * `response()->json(...)` suelto en cada sitio.
     */
    function response_success(mixed $datos = null, int $status = 200): JsonResponse
    {
        return response()->json($datos, $status);
    }
}

if (! function_exists('response_validacion')) {
    /**
     * El shape estándar de Laravel para un 422 de validación —
     * `{message, errors}` — para cuando hace falta construirlo a mano en vez
     * de dejar que `ValidationException` lo arme (p. ej. en el
     * `catch (ValidationException $e)` de `EjecutaServicio`).
     *
     * @param  array<string,array<int,string>>  $errores
     */
    function response_validacion(string $mensaje, array $errores, int $status = 422): JsonResponse
    {
        return response()->json(['message' => $mensaje, 'errors' => $errores], $status);
    }
}

if (! function_exists('response_error')) {
    /**
     * El shape para errores de dominio que NO son de validación — 403, 404,
     * 429, 500 — `{codigo, mensaje}`. `codigo` es estable y pensado para
     * lógica en el cliente; `mensaje` es para mostrar al usuario.
     *
     * `$extra` se mezcla en el cuerpo cuando una excepción concreta necesita
     * un campo más (`intentos_restantes`, `reintentar_en_segundos`...) — ver
     * `CodigoOtpIncorrecto`/`DemasiadasSolicitudesOtp`. `$headers` para casos
     * como `Retry-After`, que va en la cabecera, no en el cuerpo.
     *
     * @param  array<string,mixed>  $extra
     * @param  array<string,string>  $headers
     */
    function response_error(int $status, string $codigo, string $mensaje, array $extra = [], array $headers = []): JsonResponse
    {
        return response()->json(['codigo' => $codigo, 'mensaje' => $mensaje, ...$extra], $status, $headers);
    }
}
