---
name: endpoint
description: Crear o modificar endpoints de la API REST de FullPinta (idempotencia, autorización por rol, privacidad entre actores, prevención de N+1, formato de respuesta). Úsala al agregar rutas, controllers, form requests, resources o policies.
---

# Endpoints de la API

Fuente de verdad: `context/fullpinta-especificacion.md` §12.4, §3.2, §3.3.

REST, no GraphQL: con Flutter y un solo cliente, REST es más simple y cacheable (§12.8).

## Idempotencia — obligatoria en toda escritura (§12.4)

Todo endpoint de escritura recibe el header `Idempotency-Key` (UUID generado por el cliente). Se guarda clave → respuesta; si la clave repite, se devuelve la respuesta guardada **sin ejecutar nada**.

Aplica sin excepción a: **agendar, cancelar, registrar cobro y reseñar.**

Va en middleware, no en cada controller. La app móvil reintenta con red intermitente; sin esto se duplican citas.

```php
Route::middleware('idempotente')->group(function () {
    Route::post('/citas', [CitaController::class, 'store']);
    Route::post('/citas/{cita}/cancelar', [CitaController::class, 'cancelar']);
});
```

Que el cliente genere el UUID es también la razón por la que las PK son uuid generadas en la aplicación (ver skill `migracion`).

## Estructura del controller

**Regla dura, sin excepciones: toda acción de escritura llama a exactamente UN método de un servicio de `Application/`.** Nada de construir o actualizar un modelo directo en el controller, ni para el caso más trivial. El servicio es **uno por modelo** (`{Modelo}Service`, ver skill `modulo`), no uno por acción — si parece "demasiado simple para merecer un método", igual se crea (un método de 5 líneas es más barato que la próxima persona sin saber dónde está la lógica).

Cada acción tiene la misma forma: `$this->ejecutar(fn () => ...)`, que da el try/con-dos-catch de golpe:

```php
public function store(CrearLocalRequest $request, Negocio $negocio, LocalService $locales): JsonResponse
{
    return $this->ejecutar(fn () => LocalResource::make($locales->crear($negocio, $request->validated())), 201);
}

public function activar(Local $local, LocalService $locales): JsonResponse
{
    return $this->ejecutar(function () use ($local, $locales) {
        $this->authorize('cambiarEstado', $local);   // autorización: se queda en el controller

        return LocalResource::make($locales->activar($local));
    });
}
```

`ejecutar()` vive en el trait `App\Http\Controllers\Concerns\EjecutaServicio` (ya incluido en la `Controller` base — no hay que importarlo). Por dentro:

```php
try {
    $resultado = $servicio();
    return $resultado instanceof JsonResponse ? $resultado : response_success($resultado, $statusExito);
} catch (ValidationException $e) {
    return response_validacion($e->getMessage(), $e->errors(), $e->status);
} catch (Throwable $e) {
    return $this->traducirError($e);   // delega en render() si la excepción lo tiene, si no 403/404/500
}
```

- **Validación de formato** (¿el campo existe, tiene el tipo correcto?) → `FormRequest`. Falla antes de que el controller se ejecute; Laravel ya la renderiza como 422 con `{message, errors}` — el catch de arriba nunca la ve, pero produce EL MISMO shape para lo que sí ve.
- **Validación de regla de negocio** (¿el estado permite esta transición? ¿ese RUC ya existe?) → dentro del método del servicio, con `throw_validacion('mensaje', 'campo')`. Esto SÍ llega al catch del controller. Ver `LocalService::activar()`/`pausar()`.
- **Autorización** → `Policy` vía `$this->authorize(...)`, dentro del closure que pasa a `ejecutar()`. No es "lógica de negocio" en el sentido de arriba — es un gate de acceso, y Laravel ya lo modela bien con Policies; no se empuja al servicio.
- **Serialización** → `JsonResource`, elegido por el controller (es presentación, no negocio).
- **Todo lo demás** → el servicio en `Application/` (ver skill `modulo`).

### Los 4 helpers globales (`app/Support/helpers.php`)

Autoloaded vía `composer.json` (`autoload.files`), sin namespace — se usan igual desde cualquier módulo.

| Helper | Para qué | Shape que produce |
|---|---|---|
| `throw_validacion($mensaje, $campo = 'error')` | Rechazar una regla de negocio desde un servicio, en una línea | Lanza una `ValidationException` real (vía `withMessages()`) — no una excepción de mentira con un string |
| `response_success($datos, $status = 200)` | Éxito | El recurso tal cual, sin envolver (`JsonResource::withoutWrapping()` global) |
| `response_validacion($mensaje, $errores, $status = 422)` | 422 armado a mano (rara vez hace falta; `ejecutar()` ya lo hace por el catch) | `{message, errors}` |
| `response_error($status, $codigo, $mensaje)` | Error de dominio: 403, 404, 429, 500 | `{codigo, mensaje}` |

**Por qué dos shapes distintos** (`message`/`errors` vs. `codigo`/`mensaje`) y no uno solo: el primero es el formato nativo de Laravel para validación — lo esperan `assertJsonValidationErrors()` en los tests y cualquier cliente HTTP genérico de Laravel, así que no se traduce. El segundo es del proyecto, para todo lo que no es "este campo no cumple una regla" — autorización, no encontrado, rate limit, error interno.

`throw_validacion()` NO acepta un mensaje suelto sin más — construye la `ValidationException` real de Laravel (`ValidationException::withMessages()`), a diferencia de un patrón visto en otros proyectos que arma una excepción con un string JSON como mensaje: eso rompería `$e->errors()` (usado en `ejecutar()` y en los tests) porque `ValidationException` real exige un `Validator` en el constructor, no un string.

### Cuándo SÍ usar una excepción propia en vez de `throw_validacion()`

Casi nunca. Solo cuando el error necesita **datos que el shape `{message, errors}` no puede llevar**, o un código HTTP que no es 422:

- `DemasiadasSolicitudesOtp` — es 429, no 422, y lleva `Retry-After`.
- `CodigoOtpIncorrecto` — lleva `intentos_restantes`, un campo que no cabe en un mensaje de validación.

Esas excepciones implementan su propio `render()` (ver `app/Modules/Identity/Application/Exceptions/`, usando `response_error()` por dentro); el `catch (Throwable)` del controller las detecta solo (`method_exists($e, 'render')`) y delega, sin que el controller sepa que existen. Para cualquier otro caso — "esto no cumple una regla", con o sin FormRequest de por medio — `throw_validacion()` alcanza y es lo que hay que usar.

## Autorización: los cuatro roles (§3.2)

| Acción | Cliente | Profesional | Recepción | Propietario |
|---|:--:|:--:|:--:|:--:|
| Agendar para sí | ✓ | ✓ | ✓ | ✓ |
| Ver agenda propia | — | ✓ | — | ✓ |
| Ver agenda completa del local | — | — | ✓ | ✓ |
| Crear walk-in / registrar cobro | — | ✓ | ✓ | ✓ |
| Marcar completada / no-show | — | ✓ | ✓ | ✓ |
| Bloquear su horario | — | ✓ | — | ✓ |
| Ver sus propias comisiones | — | ✓ | — | ✓ |
| Ver comisiones de todos | — | — | **✗** | ✓ |
| Editar precios, servicios, asignaciones | — | — | — | ✓ |
| Responder reseñas | — | — | — | ✓ |
| Suscripción y facturación | — | — | — | ✓ |

**Recepción no ve comisiones de nadie.** Ese es el punto del rol: sin él, el dueño le da su propia clave a la recepcionista y se pierde el control de acceso.

Un usuario puede tener varios roles y varios locales. El contexto activo se resuelve por `negocio_miembro` / `asignacion` vigente, no por un campo `rol` en `usuario`.

## Privacidad — se aplica en el Resource (§3.3)

Estas reglas se rompen por omisión, no por descuido deliberado. Revisarlas en cada Resource nuevo:

| Situación | Regla |
|---|---|
| Local A consulta disponibilidad de un profesional que también trabaja en local B | Devolver `ocupado / otro compromiso`. **Sin local, sin cliente, sin servicio.** Si el dueño ve que su barbero atiende en la competencia, hay problema laboral y te sacan de la app. |
| Nota del local sobre el cliente | Dato del local. A nunca ve la de B. |
| Teléfono del cliente | Solo en citas `confirmada` o posterior. Sin exportación masiva. Log de accesos. |
| Comisiones | Cada profesional ve solo las suyas. |

## Una ruta anidada de dos niveles necesita AMBOS parámetros en la firma

Cuando dos recursos usan el mismo nombre genérico de hijo (p. ej. dos módulos distintos con "fotos"), `->shallow()` en ambos colisiona en la misma URI plana (`/fotos/{foto}`) y uno pisa al otro en silencio — ver el punto de arriba sobre nombres de parámetro. La solución ahí fue dejar UNO de los dos anidado, sin `shallow()`: `profesionales/{profesional}/fotos/{foto}`.

Eso trae su propia trampa: la ruta ahora expone **dos** parámetros en la URI, pero es fácil declarar el método del controller con uno solo (el que de verdad se usa) y dejar que Laravel "adivine" el otro. No adivina — pasa los valores **por posición**, así que el primer parámetro de la URI (`profesional`, un string) termina en el primer parámetro tipado del método (`ProfesionalFoto $foto`), y PHP lo rechaza con un `TypeError` en tiempo de ejecución.

```php
// ✗ La ruta es profesionales/{profesional}/fotos/{foto}, pero el método solo
// declara uno — Laravel pasa 'profesional' (string) donde espera ProfesionalFoto.
public function destroy(ProfesionalFoto $foto, ProfesionalFotoService $fotos): JsonResponse

// ✓ Los dos parámetros de la URI, en el mismo orden que aparecen en la ruta,
// aunque $profesional no se use dentro del método.
public function destroy(Profesional $profesional, ProfesionalFoto $foto, ProfesionalFotoService $fotos): JsonResponse
```

**Regla:** toda ruta con más de un `{parametro}` en la URI declara TODOS esos parámetros en la firma del método del controller, en el mismo orden, aunque alguno no se use — el servicio inyectado siempre va después de los parámetros de ruta.

## Un campo `date` en un Resource necesita `->toDateString()` explícito

El formato que se le da a un cast `date` en el modelo (aunque sea `'date:Y-m-d'`) **solo se aplica cuando se serializa el modelo directo** (`$model->toArray()`/`toJson()`). Un `JsonResource` que hace `'campo' => $this->campo` lee el atributo ya casteado (un objeto Carbon) y lo mete en su propio array — eso se salta por completo el paso donde Eloquent aplicaría el formato, así que el `json_encode()` final usa el `jsonSerialize()` por defecto de Carbon: un ISO completo con hora falsa medianoche UTC (`"2026-09-15T00:00:00.000000Z"`) en vez de solo la fecha.

```php
// ✗ Devuelve "2026-09-15T00:00:00.000000Z", no "2026-09-15"
'hasta' => $this->hasta,

// ✓ El `?->` importa: el campo puede ser NULL (asignación vigente, sin fecha de fin)
'hasta' => $this->hasta?->toDateString(),
```

**Regla:** todo campo de un modelo casteado como `date` (no `datetime`) se formatea con `?->toDateString()` en el Resource, siempre — el cast del modelo se queda como `'date'` simple, sin sufijo de formato, porque ese sufijo no resuelve nada para el camino que de verdad usa la API.

## Rendimiento

**El enemigo real no es la escala: son los N+1** (§12.8). Una pantalla de búsqueda que dispara 400 consultas duele con 50 usuarios, mucho antes de que la arquitectura importe.

- `Model::preventLazyLoading(! app()->isProduction())` en `AppServiceProvider`. Desde el día uno.
- Todo `JsonResource` de colección declara qué relaciones necesita; el controller las carga con `with()`.
- Nada de contar ni sumar dentro del Resource: llega calculado desde el caso de uso.

El 99% de la carga es lectura (§12.1). Las rutas de lectura pesadas —búsqueda, disponibilidad, perfil del local— van por caché; ver skill `disponibilidad` para la tabla de TTL y la regla de oro.

## Respuestas

- Éxito: `200` / `201` con el Resource.
- Validación: `422` con `errors` de Laravel.
- Autorización: `403`. Sin filtrar en el mensaje si el recurso existe o no.
- Slot tomado en la carrera: `409` con código `slot_ocupado`, para que la app refresque la grilla.
- Errores de dominio: excepción propia mapeada en `bootstrap/app.php`, nunca un 500 genérico.

## Observabilidad (§12.10)

Métricas que sí se miden: latencia de la consulta de disponibilidad, tasa de colisión de slots, tasa de fallo de envíos de WhatsApp, profundidad de las colas. Logs estructurados en JSON, Sentry para errores.
