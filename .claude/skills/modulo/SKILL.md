---
name: modulo
description: Crear o modificar un módulo de FullPinta (Identity, Directory, Catalog, Staffing, Scheduling, Reviews, Billing, Notifications). Úsala al agregar casos de uso, controladores, eventos de dominio o modelos, y para saber qué va dentro del módulo y qué es compartido.
---

# Módulos de FullPinta

Fuente de verdad del producto: `context/fullpinta-especificacion.md` §12.3.

## Desviación deliberada del §12.3

La especificación dice que un módulo no debe importar los modelos Eloquent de otro y que todo acceso cruzado va por puerto o evento. **En este proyecto se decidió no hacerlo así**: los 43 modelos viven juntos en `app/Models` y cualquier módulo puede usarlos.

El motivo es el costo real: `Cita` se relaciona con `local`, `profesional`, `cliente`, `recurso` y `servicio_local` — cada una cruza un módulo. Resolver eso por puertos convierte cada pantalla en ceremonia, con un solo desarrollador y sin la escala que lo justifique.

**Lo que se conserva de la idea original**, y sí hay que respetar:

- La **lógica de negocio de un módulo vive en ese módulo**. `Billing` no implementa reglas de agendamiento ni recalcula disponibilidad; `Scheduling` no decide comisiones.
- Las reacciones entre módulos van por **evento de dominio**, no por llamada directa. `Notifications` escucha; nadie lo llama.
- Los controladores son traductores, no lugar para reglas.

Si algún día se quisiera extraer `Scheduling` a un servicio propio (§12.9, etapa 3 — que la mayoría de estas apps nunca alcanza), habría que deshacer esta decisión primero. Está asumido.

## Los ocho módulos

```
app/Modules/
  Identity/       usuarios, roles, OTP, consentimientos
  Directory/      negocio, local, amenidades, verificación
  Catalog/        catálogo maestro, servicios del local, precios
  Staffing/       profesional, asignación, turnos, habilidades, recursos
  Scheduling/     disponibilidad, citas, holds, waitlist
  Reviews/        reseñas, ranking, moderación
  Billing/        suscripción, comisiones, liquidación
  Notifications/  push, WhatsApp, plantillas, log de envíos
```

No se crean módulos nuevos sin una razón fuerte. Si algo no encaja, casi siempre pertenece a uno existente.

## Qué va dónde

```
app/
  Models/                  los 43 modelos Eloquent, compartidos
  Support/                 ayudantes transversales (Esquema, etc.)
  Http/Middleware/          middleware global (ForzarJson, Idempotencia)
  Modules/<Modulo>/
    Application/           servicios: una clase por MODELO, con sus métodos
    Events/                eventos de dominio que este módulo publica
    Http/
      Controllers/
      Requests/
      Resources/
    routes.php             se monta bajo /api/v1 desde el ServiceProvider
    <Modulo>ServiceProvider.php

database/migrations/       TODAS las migraciones, no por módulo
```

Las migraciones son centrales a propósito: el esquema no es modular aunque el código lo sea. `cita` tiene claves foráneas a cinco módulos distintos. Ver skill `migracion`.

## Servicios: una clase por modelo

**No una clase por acción.** Todo lo que se puede hacer con un modelo —listar, crear, actualizar, transiciones de estado, lo que aplique— vive en un solo archivo `{Modelo}Service`, como métodos. Es la convención del proyecto desde la Fase 3: antes se probó con clases de una sola acción (`CrearLocal`, `ActivarLocal`, `PausarLocal`...) y se abandonó a propósito — con un archivo por modelo, toda la lógica de ese modelo está en un lugar predecible y se reusa igual de fácil desde cualquier controlador, comando o job.

```php
namespace App\Modules\Directory\Application;

final readonly class LocalService
{
    public function crear(Negocio $negocio, array $datos): Local { /* ... */ }
    public function actualizar(Local $local, array $datos): Local { /* ... */ }
    public function activar(Local $local): Local { /* ... */ }
    public function pausar(Local $local): Local { /* ... */ }
    public function listarPorNegocio(Negocio $negocio): Collection { /* ... */ }
}
```

- Un modelo de solo lectura (catálogo de plataforma, p. ej. `ServicioCategoria`) igual tiene su propio archivo, aunque hoy solo tenga `listar()` — el día que gane una escritura, ya tiene dónde ir.
- Dos modelos relacionados por una FK (`ServicioCategoria` y `CatalogoServicio`) **no** comparten archivo solo por estar relacionados: si son modelos distintos, son servicios distintos.
- El tiempo entra como parámetro donde importe, para que los tests sean deterministas.
- Una escritura que dispara un evento de dominio lo publica antes de retornar.
- No sobre-arquitecturar: `Scheduling` es el único módulo donde vale la pena diseño real; los demás son CRUD con reglas, y un método de 5 líneas dentro del `{Modelo}Service` es tan válido como uno de 30.

## Eventos de dominio

Los que existen: `CitaCreada`, `CitaCompletada`, `CitaCancelada`, `CitaReagendada`, `TurnoModificado`, `ResenaPublicada`.

- Se publican desde `Application/`, no desde el modelo.
- `Notifications` y las proyecciones de `Scheduling` **escuchan**; nunca se llaman entre sí directamente.
- Un listener lento (WhatsApp, push, reconstruir proyecciones) implementa `ShouldQueue` y va a su cola (ver skill `notificacion`).
- El evento lleva **ids y datos congelados**, no modelos Eloquent. Un evento serializado a la cola con un modelo dentro se resuelve tarde y lee estado que ya cambió.

```php
final readonly class CitaCompletada
{
    public function __construct(
        public string $citaId,
        public string $localId,
        public string $profesionalId,
        public string $clienteId,
        public CarbonImmutable $completadaAt,
    ) {}
}
```

## Checklist al agregar un caso de uso

1. ¿Está en `Application/` del módulo dueño de esa regla?
2. ¿El controller solo traduce HTTP → caso de uso → Resource?
3. ¿La escritura publica su evento de dominio?
4. ¿Hay policy para la acción? Ver matriz de roles abajo.
5. ¿El Resource respeta las reglas de privacidad del §3.3?

## Matriz de roles (§3.2) — se aplica en Policies

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

**Recepción es un rol aparte, no un propietario con menos permisos.** Agenda y cobra, pero no ve comisiones de nadie.

El rol no es un campo de `usuario`: se resuelve por `negocio_miembro` y `asignacion` vigentes.

## Privacidad entre actores (§3.3) — se aplica en Resources

- Local A consultando disponibilidad de un profesional que también trabaja en local B ve `ocupado / otro compromiso`. **Sin local, sin cliente, sin servicio.**
- La nota del local sobre el cliente es dato del local. A nunca ve la de B.
- Teléfono del cliente: visible solo desde `confirmada` en adelante. Sin exportación masiva, con log de accesos.
- Cada profesional ve solo sus comisiones.

## No hacer (§12.8)

Microservicios, event sourcing, GraphQL, ni nada con "distributed" en el nombre. El enemigo real son los N+1: `preventLazyLoading()` en desarrollo desde el día uno.
