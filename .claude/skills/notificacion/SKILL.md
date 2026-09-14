---
name: notificacion
description: Trabajar sobre notificaciones de FullPinta — push (FCM/APNs), WhatsApp Cloud API, WebSocket, plantillas, preferencias y control de costo. Úsala al agregar o modificar cualquier envío al cliente, al profesional o al local.
---

# Notificaciones

Fuente de verdad: `context/fullpinta-especificacion.md` §11.

## Matriz de envíos (§11.2)

| Evento | Destinatario | Canal | Cuándo |
|---|---|---|---|
| Cita creada | Cliente | Push + WhatsApp | Inmediato |
| Cita creada | Profesional / local | Push + WebSocket | Inmediato |
| Recordatorio | Cliente | Push | 24 h antes |
| Recordatorio final | Cliente | WhatsApp + push time-sensitive | 2–3 h antes |
| Cancelada por el local | Cliente | Push + WhatsApp | Inmediato |
| Cancelada por el cliente | Profesional | Push + WebSocket | Inmediato |
| Cupo liberado (waitlist) | Cliente en espera | Push + WhatsApp | Inmediato |
| Pedir reseña | Cliente | Push | 2 h después de completada |
| Reseña nueva / respuesta | Local | Push | Inmediato |
| Turno modificado | Profesional | Push | Inmediato |
| OTP de registro | Usuario | WhatsApp (authentication) | Inmediato |
| Suscripción por vencer | Propietario | Push + email | 7 días antes |

Dos reglas duras que se derivan de la tabla:

- **Al profesional nunca se le manda WhatsApp.** Tiene la app abierta todo el día y el costo lo paga la plataforma.
- **Dos recordatorios por cita, no más.** El de 24 h permite reagendar; el de 2–3 h es el que reduce el no-show. Un tercero no mejora nada y quema presupuesto.

## Anti-duplicados

```sql
UNIQUE (cita_id, tipo_evento, canal)
```

Es el seguro contra reintentos de la cola. Esa combinación decide si ya se envió. No se resuelve con una condición dentro del job.

Cada transición de estado de la cita **cancela y reprograma** las notificaciones pendientes de esa cita (§6). Una cita reagendada cuyo recordatorio viejo sigue programado manda al cliente a la hora equivocada.

## Costo de WhatsApp (§11.3)

Desde el 1/7/2025 Meta cobra por cada plantilla entregada, por categoría y país — ya no por ventana de conversación.

- Los recordatorios son **utility**, la categoría barata (utility y authentication suelen estar bajo $0.03).
- Cuenta base: 300 citas/mes × 1 recordatorio utility ≈ $6/mes contra $23 de suscripción. Sano.
- Con 4 mensajes por cita ≈ $24 y el cliente pasa a pérdida. **De ahí que casi todo vaya por push.**

Dos cambios a tener presentes:

- **La ventana gratuita se termina.** Desde el 1/10/2026 los mensajes de servicio y las respuestas utility dentro de la ventana vuelven a ser facturables. No diseñar la economía asumiendo mensajes gratis.
- **Las promociones tienen tope.** Son categoría marketing; Meta limita ~2 al día por usuario sumando todas las empresas y devuelve el error `131049`. Las promociones de hora valle del plan Pro van por **push**, no por WhatsApp.

Control obligatorio: presupuesto de mensajes por local y por mes. Al superarlo, degradar a solo push y avisar al dueño. `notificacion.costo_usd` por fila permite saber el costo real por local.

Los rate cards se actualizan trimestralmente: verificar la tarifa vigente para Ecuador antes de fijar el precio de la suscripción.

## Colas (§12.6)

| Cola | Qué lleva |
|---|---|
| `critica` | Expiración de holds (cada minuto), confirmaciones |
| `notificaciones` | WhatsApp y push, con retry y backoff |
| `proyecciones` | Reconstrucción de `disponibilidad_dia` |
| `batch` | Ranking nocturno, liquidaciones, reportes |

Ningún envío se hace dentro del request. Todo pasa por `notificaciones`.

## Preferencias del usuario

`preferencia_notificacion` es por `(usuario, categoria)` con flags `push` y `whatsapp`. Categorías: `citas`, `agenda`, `social`, `promos`.

Se respeta siempre **salvo** para lo transaccional indispensable y el OTP. Nadie se desuscribe de que le avisen que su cita se canceló.

## Errores a prevenir por diseño (§11.9)

Antes de dar por terminado un envío nuevo, revisar §11.9 de la especificación. Los recurrentes:

- Token FCM inválido o rotado → desactivar el `device_token`, no reintentar en bucle.
- iOS requiere el APNs Auth Key configurado y el `apns_token` guardado (§11.5).
- Plantilla de WhatsApp sin aprobar → el envío falla. Las plantillas se aprueban **con antelación**, no al desplegar.
- `collapse_id` para que tres actualizaciones de la misma cita no apilen tres notificaciones.

## Permisos (§11.8)

No pedir permiso de notificaciones al abrir la app por primera vez. Se pide en el momento en que tiene sentido para el usuario —al agendar su primera cita— porque un "No" inicial es difícil de revertir.

## WebSocket

Laravel Reverb, proceso aparte de la API. Solo empuja cambios a pantallas abiertas (agenda del local). **No participa en la concurrencia de agendamiento**: eso lo resuelve el constraint de Postgres (ver skill `disponibilidad`).
