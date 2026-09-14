# FullPinta — API

Backend del marketplace de agendamiento para **barberías, estéticas, uñas y grooming** en Ecuador.

Un cliente encuentra un local cerca, ve precios y disponibilidad reales, y reserva con un profesional concreto. El local gestiona su agenda, sus profesionales y sus comisiones desde la misma plataforma.

Las apps de cliente y de local son un único proyecto **Flutter**, en repositorio aparte. Este repositorio es solo la API.

---

## Estado

En construcción, fase inicial. El esquema y las reglas de negocio están especificados por completo; la implementación arranca ahora.

**Antes de escribir cualquier código, lee [`context/fullpinta-especificacion.md`](context/fullpinta-especificacion.md).** Es la fuente de verdad del producto, del modelo de datos y de las decisiones de diseño. Casi todo lo que parece arbitrario está justificado ahí.

---

## Stack

| Pieza | Elección | Por qué |
|---|---|---|
| Base de datos | **PostgreSQL 17 + PostGIS + btree_gist** | La única pieza sin alternativa. Ver abajo. |
| Backend | Laravel 13 / PHP 8.3 | Productividad: Eloquent, migraciones, colas, policies, Reverb |
| Caché y colas | Redis | |
| Tiempo real | Laravel Reverb | Proceso aparte de la API |
| Push | Firebase Cloud Messaging + APNs | |
| Mensajería | WhatsApp Cloud API | Plantillas aprobadas con antelación |
| Apps | Flutter | Un proyecto, selector de rol al entrar |

### Por qué PostgreSQL no es negociable

El agendamiento correcto bajo concurrencia no se resuelve en código de aplicación. Se resuelve con tres constraints `EXCLUDE USING gist`:

- Un profesional no puede tener dos turnos encimados, aunque sean de locales distintos.
- Un profesional no puede tener dos citas traslapadas **en ningún local** — es un solo cuerpo.
- Un recurso físico (silla, mesa de uñas, tina) no puede estar ocupado dos veces.

Dos clientes peleando por el mismo slot es el escenario normal, no el borde. "Consultar y luego insertar" falla siempre. La base es la única fuente de verdad, y PostGIS además resuelve la búsqueda por cercanía con índice espacial.

---

## Puesta en marcha

> Guía completa, con troubleshooting y la sección de producción, en [`PUESTA-EN-MARCHA.md`](PUESTA-EN-MARCHA.md).

Requisitos: PHP 8.3+, Composer, Docker.

```bash
git clone <repo> fullpinta-api && cd fullpinta-api

composer install
cp .env.example .env
php artisan key:generate

docker compose up -d           # PostgreSQL + PostGIS y Redis
php artisan migrate
```

### Servicios locales

Corren en puertos desplazados para no chocar con una instalación de Laragon/XAMPP:

| Servicio | Puerto | Credenciales |
|---|---|---|
| PostgreSQL 17 + PostGIS 3.5 | `5433` | `fullpinta` / `fullpinta`, base `fullpinta` |
| Redis 7 | `6380` | sin clave |

```bash
docker compose ps                                              # estado
docker exec -it fullpinta-postgres psql -U fullpinta -d fullpinta   # consola SQL
docker compose down                                            # detener (los datos persisten)
```

### Extensiones de PHP

Se requieren `pdo_pgsql` y `pgsql` habilitadas en `php.ini`. Si `php -m | grep pgsql` no devuelve nada, descoméntalas en tu `php.ini` y reinicia.

Redis se usa a través de **predis** (cliente en PHP puro), así que no hace falta compilar `phpredis`.

### Producción

Todavía no implementada — es la Fase 11 del [plan de implementación](context/plan-implementacion.md). La plataforma objetivo es [Laravel Cloud](https://cloud.laravel.com/) (ver [`CLAUDE.md`](CLAUDE.md)). Detalle de lo pendiente y de los requisitos de infraestructura ya conocidos en [`PUESTA-EN-MARCHA.md`](PUESTA-EN-MARCHA.md#producción).

---

## Estructura

Monolito modular. Con un solo desarrollador, los microservicios multiplican despliegues y debugging distribuido a cambio de una escala que no existe. Lo que sí hace falta son fronteras internas reales.

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

Cada módulo con `Application/` (casos de uso), `Events/` (eventos de dominio que publica) y `Http/` (controladores, requests, resources), más su `routes.php`.

**Lo compartido va fuera de los módulos:** los modelos Eloquent en `app/Models` y las migraciones en `database/migrations`. El esquema no es modular aunque el código lo sea — `cita` tiene claves foráneas que cruzan cinco módulos.

**La regla que hace que los módulos sirvan:** la lógica de negocio de un módulo vive en ese módulo. Compartir modelos no es licencia para compartir reglas — `Billing` no recalcula disponibilidad y `Scheduling` no decide comisiones. Las reacciones entre módulos van por evento de dominio, nunca por llamada directa: `Notifications` escucha, nadie lo llama.

`Scheduling` es el único módulo donde vale la pena gastar esfuerzo de diseño. Los demás son CRUD con reglas.

---

## Convenciones

| Aspecto | Regla |
|---|---|
| Claves primarias | `uuid` generado en la aplicación — permite idempotencia desde el móvil |
| Tablas | Singular, snake_case: `cita`, `servicio_local` |
| Enums | `varchar` + `CHECK`, nunca tipos nativos de Postgres |
| Timestamps | `timestamptz`, **siempre UTC** en base; `America/Guayaquil` es presentación |
| Rangos | Siempre `'[)'` — una cita que termina 10:30 y otra que empieza 10:30 no se traslapan |
| Borrado | Soft delete por `estado` o `activo`. No se borran locales, profesionales ni citas |
| Dinero | `numeric(10,2)`, nunca float |
| Idioma | Dominio en español; inglés solo donde Laravel lo exige |

Dos reglas transversales que se rompen con facilidad:

- **Se cachea para mostrar, nunca para decidir.** La grilla de horarios se pinta desde Redis; la reserva se valida contra Postgres. Confiar en el caché al escribir es sobrevender.
- **`Idempotency-Key` en toda escritura.** La app móvil reintenta con red intermitente; sin esto se duplican citas.

---

## Comandos

```bash
php artisan test                    # suite completa
php artisan test --filter=Nombre    # un test
vendor/bin/pint                     # formateo
php artisan queue:work --queue=critica,notificaciones,proyecciones,batch
php artisan migrate:fresh --seed    # rehacer la base local
```

### Tests

La suite del motor de disponibilidad no es negociable: solapamientos, turnos multi-local, traslado entre locales, recursos ocupados, excepciones, cruces de medianoche, holds vencidos, y un test de concurrencia real que lance 20 peticiones paralelas al mismo slot y verifique que **exactamente una** gana.

Es el único componente donde un bug cuesta clientes y reputación.

---

## Documentación

- [`PUESTA-EN-MARCHA.md`](PUESTA-EN-MARCHA.md) — cómo levantar el proyecto en local (con troubleshooting) y el estado de producción
- [`context/fullpinta-especificacion.md`](context/fullpinta-especificacion.md) — especificación completa: dominio, esquema, motor de slots, monetización, cumplimiento legal, alcance de la v1
- [`context/plan-implementacion.md`](context/plan-implementacion.md) — plan de trabajo por fases, con lo hecho y los supuestos tomados en cada una
- [`docs/api-referencia.md`](docs/api-referencia.md) — contrato request/response de cada endpoint, para el front de Flutter
- [`CLAUDE.md`](CLAUDE.md) — contexto y reglas para agentes de IA
- `.claude/skills/` — guías por dominio: `migracion`, `modulo`, `disponibilidad`, `endpoint`, `notificacion`

---

## Alcance de la v1

**Dentro:** registro de negocio y local, catálogo de servicios con precios, profesionales con turnos multi-local, búsqueda por cercanía con filtros, agendamiento con slots reales, agenda del local con walk-ins, cliente sombra con reclamo por OTP, reseñas post-cita, push y WhatsApp transaccional, consentimientos LOPDP.

**Fuera:** cualquier flujo de dinero por la plataforma, chat, múltiples sucursales, fidelización, liquidación de comisiones (es el gancho del plan Pro), grooming activo (modelado pero apagado), y cualquier cosa con IA.
