---
name: disponibilidad
description: Trabajar sobre el motor de disponibilidad, slots, holds y agendamiento de FullPinta (módulo Scheduling). Úsala al tocar cálculo de slots, creación de citas, concurrencia, caché de disponibilidad o la máquina de estados de la cita.
---

# Motor de disponibilidad

Fuente de verdad: `context/fullpinta-especificacion.md` §5 y §6.

**Aquí está el 90% de la dificultad del sistema y lo único que no se puede improvisar después.** Es el único componente donde un bug cuesta clientes y reputación. Si una decisión aquí parece ambigua, se consulta la especificación antes de escribir código.

## Los 10 filtros de un slot válido (§5.1)

Todos, en este orden:

1. El local está `activo` y abierto ese día/hora (`horario_local`)
2. El profesional tiene asignación vigente en ese local y **turno vigente** ese día (`turno` + overrides de `turno_fecha`)
3. El profesional tiene `habilidad` para **todos** los servicios pedidos
4. No hay `excepcion` que cubra esa ventana (local, profesional **o** recurso)
5. El profesional no tiene otra cita traslapada **en ningún local**
6. Si la cita adyacente del profesional es en otro local, hay al menos `traslado_min` de separación
7. Hay un recurso del tipo requerido libre en toda la ventana
8. `inicio >= now() + lead_time_min`
9. `inicio <= now() + horizonte_dias`
10. La ventana completa (**duración + buffer**) cabe antes del cierre del local

Trampas frecuentes:

- El filtro 3 es el que todos olvidan. Si el cliente agenda uñas y el sistema asigna al barbero que solo hace fades, hay problema el día uno.
- El filtro 5 es sobre el profesional, **no** sobre `(local, profesional)`. Ver skill `migracion`.
- El filtro 6 no lo puede validar ningún constraint: la base no sabe de geografía. Va en el motor, siempre.
- Una `excepcion` con `profesional_id` y `local_id NULL` bloquea al profesional en **todos** sus locales. No está enfermo solo en una sucursal.
- Los overrides de `turno_fecha` se aplican **después** de los turnos recurrentes vigentes, no antes.

## Granularidad

Candidatos cada **15 minutos**. No cada minuto (ruido, y el barbero no piensa así) ni cada hora (se pierden slots reales).

## Concurrencia: camino optimista, sin locks (§5.3)

Dos clientes pidiendo el mismo slot al mismo tiempo es el escenario normal, no el borde. "Consultar disponibilidad y luego insertar" **falla siempre** bajo concurrencia.

**No usar `SELECT ... FOR UPDATE` para agendar.** Serializa todas las reservas del local y agrega latencia justo en el pico. La tasa real de colisión sobre el mismo slot es baja.

```php
try {
    DB::transaction(fn () => $this->citas->crear($datos));
} catch (QueryException $e) {
    if ($e->getCode() === '23P01') {      // exclusion_violation
        throw new SlotYaOcupado();
    }
    throw $e;
}
```

`23P01` es el SQLSTATE de violación de exclusión. Al cliente se le devuelve "ese horario acaba de ocuparse" y se refresca la grilla. Cero locks, correcto bajo cualquier concurrencia, y la base es la única fuente de verdad.

**Donde sí hace falta lock:** la liquidación de comisiones (módulo `Billing`). Se bloquea el rango del periodo mientras se liquida, o se liquida una cita que se modificó a mitad del cálculo. Ahí es plata y sí importa la consistencia total.

## Holds (§5.4)

Cliente escoge slot pero no confirma → cita en `reservada` con `expira_at = now() + 10 min`. Un job de la cola `critica` la libera.

**No confiar en que el job corrió.** Filtrar también en la consulta:

```php
->where('estado', 'reservada')->where('expira_at', '>', now())
```

Si el worker se cayó 20 minutos, sin ese filtro hay slots fantasma bloqueados.

## Regla de oro del caché (§5.5)

**Se cachea para mostrar, nunca para decidir.**

La grilla se pinta desde Redis; el agendamiento se valida contra Postgres con el constraint. Si se confía en el caché al escribir, se sobrevende.

| Qué | TTL | Invalidación |
|---|---|---|
| Disponibilidad por (local, profesional, día) | 15 min | **Por evento**: cita creada/cancelada, turno o excepción modificada |
| Resultado de búsqueda por (geohash, filtros) | 60 s | Solo TTL |
| Perfil público del local | 1 h | Al editar el local |
| Score de ranking | — | Job nocturno, **nunca** en request |

La disponibilidad no puede depender solo de TTL: si cancelan una cita, ese slot tiene que aparecer ya.

## Máquina de estados (§6)

```
reservada ──confirma──> confirmada ──llega──> en_curso ──termina──> completada
    │                        │                                          │
    │ expira                 │ cancela_cliente                          └──> resena
    ▼                        │ cancela_local
 expirada                    │ no_show
                             │ reagenda
                             ▼
              cancelada_* / no_show / reagendada
```

Terminales: `completada`, `cancelada_cliente`, `cancelada_local`, `no_show`, `expirada`, `reagendada`.

- Solo `completada` habilita reseña
- Solo `completada` cuenta para ranking del local y liquidación de comisiones
- `reagendada` **no** penaliza al cliente
- Cada transición escribe una fila en `cita_evento`
- Cada transición **cancela y reprograma** las notificaciones pendientes de esa cita

Las transiciones se implementan en `Application/`, una clase por transición, y cada una publica su evento de dominio.

## Casos borde ya decididos (§5.6)

| Caso | Qué pasa |
|---|---|
| El servicio se alarga y pisa la siguiente cita | El local marca `en_curso` y el sistema avisa al siguiente cliente del retraso |
| El barbero no llegó | El local bloquea su día con `excepcion`; el sistema notifica y ofrece reagendar |
| Cliente cancela 10 min antes | `cancelada_tarde`, cuenta para su historial de confiabilidad |
| Cliente no llegó | `no_show` — métrica clave para el local y para el score del cliente |
| Walk-in creado en el local | `canal = local`, ocupa slot igual |
| Cliente con 3 no-shows | `requiere_confirmacion = true`; debe confirmar para que el slot se le reserve |

La confiabilidad del cliente **no se muestra como puntaje público al cliente**. Es hostil y lo espanta. Uso interno.

## Tests obligatorios (§5.7)

Suite propia. Ninguna de estas puede faltar:

- [x] Solapamientos simples
- [x] Turnos multi-local
- [x] Traslado entre locales (`traslado_min`)
- [x] Recursos ocupados (2 manicuristas libres, 1 sola mesa)
- [x] Excepciones de local, de profesional y de recurso
- [x] Cruces de medianoche (turno partido en dos filas)
- [x] Holds vencidos con el worker caído
- [x] **Test de concurrencia real**: 20 requests paralelos al mismo slot, exactamente uno gana

El de concurrencia no es opcional ni simulable con mocks: tiene que pegarle a Postgres de verdad.

**Cómo lograr concurrencia real sin dependencias nuevas y sin `pcntl`** (no disponible en Windows/Laragon, y el modo multi-worker del servidor embebido de PHP lo necesita): levantar varios procesos `php -S` independientes (procesos de sistema operativo reales, no hilos) en puertos distintos — usar el router `vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`, cwd `public_path()` — y repartir las peticiones entre ellos con `curl_multi_*`. Un solo proceso `php -S` es de un único worker y serializaría las 20 peticiones, dejando de probar concurrencia real. Ver `tests/Feature/Scheduling/ConcurrenciaCitaTest.php`.

**Ese test NO puede usar `RefreshDatabase`.** Ese trait envuelve el test en una transacción que nunca se confirma (rollback al final). Los procesos `php -S` hijos abren su propia conexión a Postgres y no pueden ver filas de una transacción ajena sin confirmar — con `RefreshDatabase`, cada petición al servidor de prueba responde 401/404 porque, desde su conexión, nada de lo que el test creó existe todavía. La solución: crear los datos con `COMMIT` real (sin `RefreshDatabase`) y borrarlos a mano en `tearDown()`, en el orden que exigen las FK.

Dos trampas al borrar a mano (sin la red de seguridad de `RefreshDatabase`):

1. **Las factories arrastran su propia cadena implícita.** `Local::factory()->create()` sin `negocio_id` crea un `Negocio` (que a su vez crea un `Usuario` dueño); si no se rastrea y borra esa cadena completa, queda basura permanente en la base.
2. **Una factory puede crear datos como efecto colateral aunque el valor se sobrescriba después.** `CatalogoServicio::factory()->create(['tipo_recurso_id' => $x])` de todas formas ejecuta la resolución/creación por defecto de un `TipoRecurso` "silla" dentro de `definition()` **antes** de que el `create()` aplique el override — PHP evalúa el array completo antes del merge. Bajo `RefreshDatabase` es inofensivo (se revierte con todo lo demás); sin él, queda ahí para siempre y puede chocar con otro test que sí dependa de que "silla" no exista aún (`UNIQUE` en `codigo`). Si hace falta un valor concreto y no se puede usar la factory de forma segura, se arma la fila directo con `Model::create([...])`, sin pasar por `definition()`.

## WebSocket

**No ayuda con la concurrencia de agendamiento.** Dos clientes peleando por el mismo slot se resuelve en el constraint de Postgres; el WebSocket solo empuja el cambio a las pantallas después.
