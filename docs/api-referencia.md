# FullPinta — Referencia de API

Contrato request/response para el equipo de Flutter. Se actualiza en cada fase del [plan de implementación](../context/plan-implementacion.md) — si un endpoint existe en el código y no aparece aquí, es un defecto a corregir, no una omisión aceptable.

Para el *porqué* de cada regla de negocio, ver `context/fullpinta-especificacion.md`. Este documento solo describe el *contrato HTTP*.

## Convenciones generales

- Base: `/api/v1`.
- Todo en JSON. `Content-Type: application/json`, `Accept: application/json`.
- Autenticación: `Authorization: Bearer <token>` (Sanctum). No hay cookies ni CSRF.
- Fechas y horas: **UTC** en toda la API. La conversión a `America/Guayaquil` es responsabilidad del cliente.
- Toda escritura (`POST`/`PUT`/`PATCH`/`DELETE`) que quede marcada como **idempotente** requiere el header `Idempotency-Key: <uuid-v4-generado-por-el-cliente>`. Reintentar con la misma clave devuelve la respuesta original sin repetir el efecto.
- Errores de negocio: cuerpo `{ "codigo": "...", "mensaje": "..." }` más campos propios del caso. `codigo` es estable y pensado para lógica en el cliente; `mensaje` es para mostrar al usuario, no para parsear.
- Errores de validación: `422` con el formato estándar de Laravel — `{ "message": "...", "errors": { "campo": ["..."] } }`.

---

## Identity — Autenticación

Tres métodos de entrada — teléfono+OTP, Google, correo/contraseña — pero **un solo teléfono obligatorio y una sola forma de verificarlo** (el OTP de esta misma sección), venga la cuenta de donde venga.

### Teléfono + OTP

Registro e inicio de sesión son el **mismo par de endpoints**: quién resulte ser el teléfono (nuevo, cliente sombra reclamable, o ya registrado) lo decide el servidor al verificar el código.

### `POST /auth/otp/solicitar`

Pide un código de 6 dígitos. Público, sin autenticación. No idempotente (reintentar reenvía el código, no duplica nada).

**Body**
```json
{ "telefono": "0991234567" }
```
`telefono`: celular ecuatoriano, formato `09` + 8 dígitos.

**200**
```json
{ "mensaje": "Si el número es válido, se envió un código.", "expira_en_minutos": 5 }
```

**Errores**
| Código HTTP | `codigo` | Cuándo |
|---|---|---|
| 422 | — (validación estándar) | Teléfono con formato inválido |
| 429 | `otp_demasiadas_solicitudes` | Más de 3 solicitudes en 10 minutos para ese teléfono. Header `Retry-After` con los segundos de espera |

### `POST /auth/otp/verificar`

Verifica el código y devuelve el usuario + token. Público.

**Body**
```json
{ "telefono": "0991234567", "codigo": "482913", "nombre": "Ana Pérez" }
```
`nombre`: **obligatorio solo si el teléfono es nuevo** (no existe cuenta ni cliente sombra con ese número). Si el teléfono ya tiene cuenta, se ignora — salvo que sea un cliente sombra sin reclamar, donde reemplaza el nombre que puso la recepción.

**201**
```json
{
  "usuario": {
    "id": "uuid", "telefono": "0991234567", "telefono_verificado": true,
    "nombre": "Ana Pérez", "email": null, "foto_url": null
  },
  "token": "1|abc123..."
}
```
Guardar `token` y mandarlo en `Authorization: Bearer` desde aquí en adelante.

**Errores**
| HTTP | Forma | Cuándo |
|---|---|---|
| 422 | Validación estándar, campo `codigo` | No hay código pendiente para ese teléfono (expiró, ya se usó, o nunca se pidió) |
| 422 | Validación estándar, campo `nombre` | Teléfono nuevo sin `nombre` |
| 422 | `{ "codigo": "otp_incorrecto", "intentos_restantes": N }` | El código no coincide — este SÍ lleva un campo extra, por eso no es validación estándar |

"Validación estándar" es el formato `{ "message": "...", "errors": { "campo": ["..."] } }` de las Convenciones generales — se distingue de un error de negocio porque no necesita cargar ningún dato más allá del mensaje.

### Google

### `POST /auth/google`

Login o registro con Google. Público.

**Body**
```json
{ "id_token": "eyJhbGciOi...", "telefono": "0991234567" }
```
`id_token` es el ID token que la app obtiene de Google Sign-In (`sub`, `email`, `name`). `telefono`: mismo formato que el resto de la API (`09` + 8 dígitos) — **obligatorio siempre**, aunque se ignora si la cuenta resuelta ya tenía uno (ver abajo).

Resolución del usuario, en este orden:
1. Ya existe una cuenta con ese `google_id` (login de vuelta) → se ignora el `telefono` del body.
2. No existe por `google_id`, pero el `email` del token ya es de otra cuenta (creada por OTP o por correo) → se **enlaza** `google_id` a esa cuenta — el token de Google ya prueba que la persona es dueña de ese correo. Se ignora el `telefono` del body.
3. Ninguno existe → se crea una cuenta nueva con el `telefono` del body, `telefono_verificado: false`.

**201**: mismo shape que `POST /auth/otp/verificar`.

**Errores**
| HTTP | `codigo` | Cuándo |
|---|---|---|
| 401 | `credencial_google_invalida` | El `id_token` no es válido, expiró, o no es para esta app |
| 422 | Validación estándar, campo `telefono` | Formato inválido, o ya está en uso por otra cuenta (solo aplica al crear una cuenta nueva, caso 3) |

### Correo y contraseña

### `POST /auth/registro`

Público.

**Body**
```json
{ "nombre": "Ana Pérez", "email": "ana@example.com", "password": "algo-de-8-o-mas", "telefono": "0991234567" }
```
Todos obligatorios. `email` y `telefono` deben ser únicos — **422** (validación estándar) si cualquiera ya existe. La cuenta nace con `telefono_verificado: false`.

**201**: mismo shape que `POST /auth/otp/verificar`.

### `POST /auth/login`

Público.

**Body**: `{ "email": "ana@example.com", "password": "algo-de-8-o-mas" }`.

**200**: mismo shape que `POST /auth/otp/verificar`.

**Errores**
| HTTP | `codigo` | Cuándo |
|---|---|---|
| 422 | Validación estándar, campo `password` | Correo o contraseña incorrectos (mismo mensaje para ambos casos, no se revela cuál) |
| 429 | `demasiados_intentos_login` | Más de 5 intentos en 10 minutos para ese correo. Header `Retry-After` |

Una cuenta creada por OTP o por Google nunca puede entrar por acá a menos que además haya hecho `POST /auth/registro` con ese mismo correo — `password_hash` en esos casos es un hash aleatorio e inutilizable, nunca coincide con ninguna contraseña real.

### Verificar el teléfono después de Google o correo

Si la cuenta nació con `telefono_verificado: false`, se verifica con el **mismo** `POST /auth/otp/solicitar` + `POST /auth/otp/verificar` de arriba, usando el teléfono ya registrado — no hace falta (ni existe) un mecanismo de verificación distinto por método. `POST /auth/otp/verificar` reconoce que el teléfono ya tiene cuenta y solo actualiza `telefono_verificado: true`, sin crear una cuenta nueva.

### `GET /auth/contexto` 🔒

Con qué "sombreros" puede entrar este usuario — la base del selector de contexto (§3.2: un cliente puede ser también dueño de un negocio y barbero en otro local, todo a la vez).

**200**
```json
{
  "usuario_id": "uuid",
  "es_cliente": true,
  "requiere_seleccion": true,
  "contextos": [
    { "tipo": "negocio", "rol": "propietario", "negocio_id": "uuid",
      "negocio_nombre": "Barbería Kevin", "local_id": null, "local_nombre": null },
    { "tipo": "profesional", "rol": "barbero", "negocio_id": null,
      "negocio_nombre": null, "local_id": "uuid", "local_nombre": "Alborada" }
  ]
}
```

- `es_cliente` es siempre `true` — no es un contexto seleccionable, toda cuenta puede agendar para sí misma.
- `requiere_seleccion: false` (0 o 1 contexto) → el front entra directo, sin mostrar el selector.
- `tipo: "negocio"`: `local_id: null` significa **todos los locales de ese negocio**, no ninguno.
- `tipo: "profesional"`: el usuario tiene perfil profesional y trabaja en ese `local_id` con ese `rol` (`barbero`, `estilista`, `manicurista`, `groomer`).
- El mismo `local_id` puede repetirse con `tipo` distinto (el dueño que también corta pelo ahí) — no se deduplican, son selecciones independientes.
- Este endpoint no fija ninguna sesión de servidor: el front guarda el contexto elegido y lo manda en cada petición que lo necesite.

### `POST /auth/logout` 🔒

Revoca **solo el token con el que se autenticó esta petición** — no todos los dispositivos. Cerrar sesión en el teléfono no debe desloguear la tablet de recepción.

**204**, sin cuerpo.

---

## Identity — Consentimientos y cuenta

### `GET /consentimientos` 🔒

El estado más reciente de cada finalidad que el usuario tocó alguna vez (§13.1).

**200**
```json
[
  { "finalidad": "operacion_servicio", "otorgado": true, "vigente": true,
    "documento_version": "2026-01", "otorgado_at": "...", "revocado_at": null },
  { "finalidad": "marketing", "otorgado": true, "vigente": false,
    "documento_version": "2026-01", "otorgado_at": "...", "revocado_at": "..." }
]
```
Una finalidad ausente de la lista significa que nunca se le preguntó al usuario por ella.

### `POST /consentimientos` 🔒

Otorga o revoca una finalidad puntual. **Nunca "aceptar todo" con un solo toque** — cada finalidad es su propia decisión.

**Body**
```json
{ "finalidad": "marketing", "otorgado": true }
```
`finalidad` ∈ `operacion_servicio | comunicaciones_transaccionales | marketing | transferencia_internacional`.

**200** — mismo shape que una fila de `GET /consentimientos`. Si se pidió revocar algo que nunca se otorgó, responde `{ "finalidad": "...", "otorgado": false, "vigente": false }` sin error: es idempotente a propósito.

### `DELETE /cuenta` 🔒

Derecho de eliminación (§13.1). **No borra la cuenta** — el historial de citas cuelga de ese `usuario_id` y tiene que seguir cuadrando — la anonimiza (teléfono, nombre, email, foto quedan irreconocibles) y revoca todos sus tokens, incluido el que se usó para esta misma petición.

**204**, sin cuerpo. Sin vuelta atrás: no hay endpoint para deshacerlo.

---

## Identity — Favoritos

Un favorito es de un **local** o de un **profesional**, nunca de ambos (§4.3, mismo `CHECK` que la tabla).

### `GET /mis-favoritos` 🔒

**200**:
```json
[
  { "id": "uuid", "local": { "id": "uuid", "nombre": "...", "...": "..." }, "profesional": null, "created_at": "..." },
  { "id": "uuid", "local": null, "profesional": { "id": "uuid", "nombre": "...", "...": "..." }, "created_at": "..." }
]
```
`local`/`profesional` usan el mismo shape público de `GET /locales/{local}/perfil-publico` y `GET /profesionales/{profesional}/perfil-publico` — nunca el Resource administrativo, un favorito puede ser de un local ajeno al usuario.

### `POST /favoritos` 🔒

Un solo endpoint hace de alta y baja: si ya era favorito, lo quita; si no, lo agrega.

**Body**: `{ "local_id": "uuid" }` **o** `{ "profesional_id": "uuid" }` — nunca ambos a la vez (**422** si se mandan los dos, o ninguno).

**200**:
```json
{ "agregado": true, "favorito": { "id": "uuid", "local": {"...": "..."}, "profesional": null, "created_at": "..." } }
```
`agregado: false` → se quitó de favoritos; `favorito` viene `null` en ese caso.

---

## Directory — Negocio y local

### `POST /negocios` 🔒

Crea un negocio y convierte a quien lo crea en su propietario (§4.4) — provisiona `negocio_miembro(rol: propietario, local_id: null)` en la misma transacción.

**Body**: `{ "nombre_marca": "Barbería Kevin", "ruc": "1234567890001" }` (`ruc` opcional, 13 dígitos).

**201** — el negocio creado, `plan: "free"` por defecto.

### `GET /negocios/{negocio}` 🔒 · `PATCH /negocios/{negocio}` 🔒

Solo propietario o admin del negocio (§3.2). `PATCH` acepta `nombre_marca` y/o `ruc`.

### `POST /negocios/{negocio}/locales` 🔒 · `GET /negocios/{negocio}/locales` 🔒

Crear: solo propietario/admin. Listar: cualquier miembro con rol vigente (propietario, admin **o recepción** — a diferencia de `GET /negocios/{negocio}`, que es solo para quien administra el negocio).

**Body de creación**:
```json
{ "nombre": "Sucursal Alborada", "direccion": "Av. Principal 123", "referencia": "diagonal al parque",
  "lat": -2.1300, "lng": -79.8862, "telefono": "042345678", "whatsapp": "0991234567" }
```
`lat`/`lng` son números planos, no un objeto anidado. El local nace en `estado: "borrador"` — no aparece en búsquedas hasta activarlo.

### `GET /locales/{local}` 🔒 · `PATCH /locales/{local}` 🔒

`GET`: cualquier miembro con rol en el local. `PATCH`: solo propietario/admin — acepta cualquier campo de la creación, todos opcionales. Si se manda `lat` sin `lng` (o viceversa), se conserva el valor existente del otro — no hace falta mandar ambos.

**Respuesta** (mismo shape en todos los endpoints de local):
```json
{ "id": "uuid", "negocio_id": "uuid", "nombre": "...", "direccion": "...", "referencia": null,
  "lat": -2.13, "lng": -79.8862, "telefono": "...", "whatsapp": "...", "verificado": false,
  "estado": "borrador", "lead_time_min": 60, "horizonte_dias": 30, "politica_cancelacion_horas": 2 }
```

### `POST /locales/{local}/activar` 🔒 · `POST /locales/{local}/pausar` 🔒

Sin body. Solo propietario/admin. `local.estado` es una máquina de estados, no un booleano (§4.4):

- `activar`: válido desde `borrador` o `pausado`. Devuelve **422** (validación estándar, campo `estado`) si el local está `activo` o `suspendido`.
- `pausar`: válido solo desde `activo`. El dueño puede reactivarlo cuando quiera.
- `suspendido` no tiene endpoint propio todavía: lo pone la plataforma (moderación), no el dueño, y una vez ahí no hay forma de salir sin soporte — ver `context/plan-implementacion.md` (raíz del repo).

Ambos devuelven el local completo (mismo shape de arriba) con el `estado` ya actualizado.

---

## Directory — Horarios del local

### `GET /locales/{local}/horarios` 🔒 · `POST /locales/{local}/horarios` 🔒

`GET`: cualquier miembro con rol en el local. `POST`: solo propietario/admin.

**Body de creación**:
```json
{ "dia_semana": 1, "abre": "09:00", "cierra": "19:00" }
```
`dia_semana`: `0` = domingo … `6` = sábado. `abre`/`cierra`: formato `"HH:mm"` (24 h), **sin segundos**. Varias filas con el mismo `dia_semana` son válidas — es como se parte la jornada (mañana/tarde).

**201 / 200** (según sea `POST` o `GET`, este último devuelve un array):
```json
{ "id": "uuid", "local_id": "uuid", "dia_semana": 1, "abre": "09:00", "cierra": "19:00" }
```

**Error**: `422`, validación estándar campo `cierra`, si `cierra` no es posterior a `abre`.

### `PATCH /horarios/{horario}` 🔒 · `DELETE /horarios/{horario}` 🔒

Solo propietario/admin del local dueño de ese horario. `PATCH` acepta `dia_semana`/`abre`/`cierra`, todos opcionales — si se manda solo uno de `abre`/`cierra`, el otro se toma del valor ya guardado antes de validar que `cierra > abre`.

`DELETE` **borra la fila de verdad** (no hay `activo` en esta tabla, ver skill `migracion`) — **204**, sin cuerpo.

---

## Directory — Amenidades

### `GET /amenidades`

**Público**, sin autenticación. Catálogo completo de la plataforma (§4.4) — el front lo usa para armar el selector al configurar un local.

**Query opcional**: `?categoria=confort` (una de `confort, entretenimiento, ninos, accesibilidad, pago, politica`).

**200**:
```json
[
  { "id": "uuid", "codigo": "wifi", "categoria": "confort", "nombre": "WiFi", "icono": "wifi" },
  { "id": "uuid", "codigo": "acepta_mascotas_en_sala", "categoria": "politica", "nombre": "Acepta mascotas en sala", "icono": "dog" }
]
```

### `GET /locales/{local}/amenidades` 🔒 · `PUT /locales/{local}/amenidades` 🔒

`GET`: cualquier miembro con rol en el local — las amenidades que ese local ya tiene marcadas, con su `detalle`. `PUT`: solo propietario/admin — **reemplaza el conjunto completo**, no agrega ni quita una por una. Mandar la lista final que se quiere que quede; una lista vacía `{"amenidades": []}` las quita todas.

**Body**:
```json
{
  "amenidades": [
    { "amenidad_id": "uuid" },
    { "amenidad_id": "uuid", "detalle": "Cerveza artesanal" }
  ]
}
```
`amenidad_id` debe ser una amenidad activa del catálogo (§ arriba) — una inactiva o inexistente responde `422`. `detalle` es opcional, texto libre corto ("PS5", "cerveza artesanal").

**200** (mismo shape en ambos endpoints — `detalle` solo aparece aquí, nunca en el catálogo plano de `GET /amenidades`):
```json
[
  { "id": "uuid", "codigo": "wifi", "categoria": "confort", "nombre": "WiFi", "icono": "wifi", "detalle": null },
  { "id": "uuid", "codigo": "cerveza", "categoria": "confort", "nombre": "Cerveza", "icono": "beer", "detalle": "Cerveza artesanal" }
]
```

---

## Directory — Fotos del local

### `GET /locales/{local}/fotos` 🔒 · `POST /locales/{local}/fotos` 🔒

`GET`: cualquier miembro. `POST`: solo propietario/admin. La subida del archivo en sí (a un storage) no es parte de esta API todavía — este endpoint solo registra la URL ya subida.

**Body de creación**:
```json
{ "url": "https://...", "tipo": "fachada", "orden": 0 }
```
`tipo` ∈ `fachada | interior | trabajo`. `orden` opcional (por defecto `0`, ordena el carrusel).

**201 / 200**:
```json
{ "id": "uuid", "local_id": "uuid", "url": "https://...", "tipo": "fachada", "orden": 0 }
```

### `PATCH /fotos/{foto}` 🔒 · `DELETE /fotos/{foto}` 🔒

Solo propietario/admin del local dueño de la foto. `PATCH` acepta `url`/`tipo`/`orden`, todos opcionales (para reordenar el carrusel sin volver a mandar la URL). `DELETE` borra la fila de verdad — **204**, sin cuerpo.

---

## Directory — Búsqueda

### `GET /buscar/locales`

**Público**, sin autenticación (§7.1-7.3, §8) — la puerta de entrada del cliente antes de tener cuenta. Busca locales `activo` dentro de un radio, ordenados por `score_ranking DESC, distancia ASC`.

**Query**: `?lat=-2.13&lng=-79.8862&radio_m=5000&vertical=barberia&catalogo_servicio_id=uuid&precio_min=5&precio_max=30&amenidades[]=wifi&amenidades[]=parqueo&disponible=true&fecha=2026-09-22&abierto_ahora=true&page=1&limit=20`

`lat`/`lng` obligatorios. `radio_m` opcional (100-50000, por defecto 5000). `vertical` es el `codigo` de la vertical, no su id. `amenidades[]` exige **todas** las pedidas, no cualquiera (un local con wifi pero sin parqueo no aparece si se piden ambas). `disponible` usa `fecha` si viene, o "hoy" — consulta el read-model `disponibilidad_dia`, no corre el motor de slots por cada resultado. `abierto_ahora` compara contra el horario del día y la hora actual en UTC.

**200**:
```json
[
  { "id": "uuid", "negocio_id": "uuid", "nombre": "Sucursal Alborada", "direccion": "...",
    "lat": -2.13, "lng": -79.8862, "distancia_m": 350.2, "telefono": "...", "whatsapp": "...",
    "verificado": true, "score_ranking": 4.8 }
]
```

Resultado cacheado 60 s por (coordenadas redondeadas a 2 decimales, ~1 km, más el resto de filtros) — sustituto pragmático de un geohash real, no hay librería instalada. No es un caché para decidir: agendar sigue validándose contra Postgres.

### `GET /locales/{local}/perfil-publico`

**Público**, sin autenticación (§7.4). **404** si el local no está `activo` — no confirma al público que existe pero está oculto (pausado/suspendido/borrador). Cacheado 1 h, invalidado al editar/activar/pausar el local.

**200**:
```json
{
  "id": "uuid", "nombre": "Sucursal Alborada", "direccion": "...", "referencia": null,
  "lat": -2.13, "lng": -79.8862, "telefono": "...", "whatsapp": "...",
  "verificado": true, "score_ranking": 4.8, "lead_time_min": 60, "horizonte_dias": 30,
  "horarios": [{ "id": "uuid", "local_id": "uuid", "dia_semana": 1, "abre": "09:00", "cierra": "19:00" }],
  "servicios": [{ "id": "uuid", "local_id": "uuid", "catalogo_servicio_id": "uuid", "nombre": "Corte fade", "precio": "8.50", "..." : "..." }],
  "amenidades": [{ "id": "uuid", "codigo": "wifi", "categoria": "confort", "nombre": "WiFi", "icono": "wifi" }],
  "fotos": [{ "id": "uuid", "local_id": "uuid", "url": "https://...", "tipo": "fachada", "orden": 0 }],
  "resenas": { "promedio": 4.7, "total": 32 },
  "es_favorito": false
}
```
Solo servicios `activo: true` y reseñas `publicada`. `resenas.promedio` es el promedio de `puntaje_local` (0 si no hay ninguna).

`es_favorito`: `true` si el `Bearer` del request corresponde a un cliente que tiene este local en `GET /mis-favoritos`; `false` si no hay favorito o si la petición viene sin `Authorization` (la ruta sigue siendo pública, el header es opcional). Se calcula en cada request, **nunca** desde el bloque cacheado 1 h — si entrara ahí, el primer usuario que "calienta" el caché le fijaría su propio valor a cualquiera que lo lea después.

---

## Catalog — Catálogo maestro

Ambos endpoints son **públicos**, sin autenticación — el front los necesita para armar el selector de servicios antes de que exista ninguna cuenta o local.

### `GET /catalogo/categorias`

**Query opcional**: `?vertical=barberia` (una de `barberia, estetica, unas, mascotas`).

**200**:
```json
[{ "id": "uuid", "vertical": "barberia", "codigo": "corte", "nombre": "Cortes", "icono": "scissors", "orden": 1 }]
```

### `GET /catalogo/servicios`

**Query opcional**: `?vertical=barberia&categoria=corte` (`categoria` es el `codigo` de una categoría, no su nombre).

**200**:
```json
[
  { "id": "uuid", "vertical": "barberia", "categoria_codigo": "corte", "nombre": "Corte fade",
    "slug": "barberia-corte-fade", "duracion_base_min": 40, "tipo_recurso": "silla" }
]
```
`duracion_base_min` y `tipo_recurso` son solo una sugerencia de la plataforma — cada local fija su propia duración y precio al dar de alta el servicio (ver abajo).

---

## Catalog — Servicios del local

### `GET /locales/{local}/servicios` 🔒 · `POST /locales/{local}/servicios` 🔒

`GET`: cualquier miembro con rol en el local. `POST`: solo propietario/admin.

**Body de creación**:
```json
{ "catalogo_servicio_id": "uuid", "precio": 8.50, "precio_desde": false,
  "duracion_min": 30, "buffer_min": 5, "comisionable": true }
```
`catalogo_servicio_id` debe ser un servicio activo del catálogo maestro (`GET /catalogo/servicios`). `precio_desde`, `buffer_min`, `comisionable` son opcionales — `false`, `0` y `true` respectivamente si se omiten. Un mismo local no puede dar de alta el mismo `catalogo_servicio_id` dos veces (**422**, campo `catalogo_servicio_id`, si ya existe).

**201 / 200**:
```json
{ "id": "uuid", "local_id": "uuid", "catalogo_servicio_id": "uuid", "nombre": "Corte fade",
  "precio": "8.50", "precio_desde": false, "duracion_min": 30, "buffer_min": 5,
  "comisionable": true, "activo": true, "tamanos": [] }
```
`precio` viaja como **string** ("8.50"), no como número — es un `numeric(10,2)` de Postgres, y así es como Eloquent lo serializa (evita el redondeo binario de los floats). `nombre` viene del catálogo maestro, no se guarda en `servicio_local`. `tamanos` solo trae filas si se sincronizaron (ver abajo) — para la vertical `mascotas`, modelada pero no activada en la v1.

### `PATCH /servicios/{servicio}` 🔒 · `DELETE /servicios/{servicio}` 🔒

Solo propietario/admin del local dueño del servicio. `PATCH` acepta cualquier campo de la creación menos `catalogo_servicio_id` (ese no cambia; se da de baja y se crea uno nuevo si hace falta), todos opcionales.

`DELETE` **no borra la fila**: pone `activo: false` (§4.2) — las citas ya agendadas contra este servicio guardan su precio congelado en `cita_item` y no deben verse afectadas. **204**, sin cuerpo.

### `PUT /servicios/{servicio}/tamanos` 🔒

Solo propietario/admin. Reemplaza el conjunto completo de precios por tamaño — obligatorio para la vertical mascotas (bañar un yorkshire no cuesta lo mismo que un golden). Igual que la sincronización de amenidades: se manda la lista final, una lista vacía los quita todos.

**Body**:
```json
{ "tamanos": [
  { "tamano": "pequeno", "precio": 15, "duracion_min": 30 },
  { "tamano": "grande", "precio": 25, "duracion_min": 50 }
] }
```
`tamano` ∈ `muy_pequeno | pequeno | mediano | grande | gigante`, sin repetirse dentro de la misma lista.

**200**: array de `{ "tamano", "precio", "duracion_min" }`.

---

## Catalog — Productos

### `GET /locales/{local}/productos` 🔒 · `POST /locales/{local}/productos` 🔒

`GET`: cualquier miembro. `POST`: solo propietario/admin. Productos (pomada, cera, shampoo) llevan su propio porcentaje de comisión, distinto al de un servicio (§4.5) — importan para que la liquidación de comisiones sea correcta, aunque esa liquidación esté fuera de la v1.

**Body de creación**:
```json
{ "nombre": "Pomada", "precio": 12.50, "comision_pct": 10 }
```
`comision_pct` opcional, `0` a `100`, `0` si se omite.

**201 / 200**:
```json
{ "id": "uuid", "local_id": "uuid", "nombre": "Pomada", "precio": "12.50", "comision_pct": "0.00", "activo": true }
```

### `PATCH /productos/{producto}` 🔒 · `DELETE /productos/{producto}` 🔒

Solo propietario/admin. `PATCH` acepta `nombre`/`precio`/`comision_pct`, todos opcionales. `DELETE` desactiva (`activo: false`), no borra — mismo motivo que en servicios. **204**, sin cuerpo.

---

## Catalog — Solicitudes al catálogo maestro

Cómo un local pide que la plataforma agregue un servicio que le falta (§4.5).

### `GET /locales/{local}/solicitudes-catalogo` 🔒 · `POST /locales/{local}/solicitudes-catalogo` 🔒

`GET`: cualquier miembro. `POST`: solo propietario/admin.

**Body de creación**:
```json
{ "vertical": "barberia", "nombre_propuesto": "Afeitado a navaja", "descripcion": "Con toalla caliente" }
```
`descripcion` opcional.

**201 / 200**:
```json
{ "id": "uuid", "local_id": "uuid", "vertical": "barberia", "nombre_propuesto": "Afeitado a navaja",
  "descripcion": "Con toalla caliente", "estado": "pendiente", "motivo_rechazo": null }
```

**Sin aprobar/rechazar todavía**: eso lo haría un panel de soporte de plataforma, que no existe como concepto de autenticación en el proyecto. Por ahora se revisan a mano, directo en la base — ver `context/plan-implementacion.md`.

---

## Staffing — Profesionales

### `GET /locales/{local}/profesionales` 🔒 · `POST /locales/{local}/profesionales` 🔒

`GET`: cualquier miembro con rol en el local. `POST`: solo propietario/admin — da de alta un profesional **nuevo** junto con su primera asignación a este local (§4.6). Para sumar uno que ya existe a otro local, ver `POST /locales/{local}/asignaciones` más abajo.

**Body de creación** (dos grupos: datos del profesional + su primera asignación):
```json
{ "nombre": "Kevin Ruiz", "alias": "Kevin", "bio": "10 años de experiencia", "foto_url": "https://...",
  "independiente": false, "perfil_publico": true, "traslado_min": 30,
  "rol": "barbero", "modalidad": "empleado", "comision_pct": 50, "desde": "2026-09-01" }
```
`alias`, `bio`, `foto_url`, `independiente`, `perfil_publico`, `traslado_min`, `desde` son opcionales (`independiente: false`, `perfil_publico: true`, `traslado_min: 30` si se omiten). `rol` ∈ `barbero | estilista | manicurista | groomer | recepcion`. `modalidad` ∈ `empleado | renta_silla | invitado`.

**201 / 200**:
```json
{ "id": "uuid", "nombre": "Kevin Ruiz", "alias": "Kevin", "bio": "10 años de experiencia",
  "foto_url": "https://...", "independiente": false, "perfil_publico": true, "traslado_min": 30,
  "tiene_cuenta_propia": false }
```
`tiene_cuenta_propia` indica si el profesional tiene `usuario_id` (puede loguearse él mismo) — muchos profesionales no tienen cuenta y los administra el local.

### `GET /profesionales/{profesional}` 🔒 · `PATCH /profesionales/{profesional}` 🔒

Autorización especial: un profesional **no pertenece a un solo local** (§4.6, puede trabajar en varios) — el acceso se resuelve como "¿administrás AL MENOS UN local donde este profesional tiene una asignación vigente?", no contra un local fijo.

Sin `DELETE`: un profesional no se borra nunca; su vínculo laboral se termina con fecha (`POST /asignaciones/{asignacion}/terminar`, ver abajo).

**200**: mismo shape que la creación.

### `GET /profesionales/{profesional}/perfil-publico`

**Público**, sin autenticación (§7.5). **404** (no 403) si `perfil_publico: false` — no confirma que el profesional existe pero está oculto.

**200**:
```json
{
  "id": "uuid", "nombre": "Kevin Ruiz", "alias": "Kevin", "bio": "10 años de experiencia",
  "foto_url": "https://...",
  "fotos": [{ "id": "uuid", "profesional_id": "uuid", "url": "https://...", "orden": 0 }],
  "servicios": [{ "id": "uuid", "profesional_id": "uuid", "servicio_local_id": "uuid", "servicio_nombre": "Corte fade", "precio_override": null }],
  "resenas": { "promedio": 4.9, "total": 18 },
  "es_favorito": false
}
```
Sin `traslado_min` ni nada operativo. `resenas.promedio` es el promedio de `puntaje_profesional` (0 si no hay ninguna). `servicios` sale de las habilidades del profesional (qué atiende), no de un catálogo aparte.

`es_favorito`: igual regla que en `GET /locales/{local}/perfil-publico` — `true` solo si el `Bearer` (opcional) corresponde a un cliente con este profesional en `GET /mis-favoritos`.

---

## Staffing — Fotos del profesional

### `GET /profesionales/{profesional}/fotos` 🔒 · `POST /profesionales/{profesional}/fotos` 🔒

Igual patrón que las fotos del local (portafolio de trabajos). `POST`: requiere poder `actualizar` sobre el profesional.

**Body de creación**: `{ "url": "https://...", "orden": 0 }` (`orden` opcional, por defecto `0`).

**201 / 200**: `{ "id": "uuid", "profesional_id": "uuid", "url": "https://...", "orden": 0 }`

### `PATCH /profesionales/{profesional}/fotos/{foto}` 🔒 · `DELETE /profesionales/{profesional}/fotos/{foto}` 🔒

`PATCH` acepta `url`/`orden`, ambos opcionales. `DELETE` borra la fila de verdad (sin `activo`/`estado`) — **204**, sin cuerpo.

---

## Staffing — Asignaciones

Cómo un profesional que **ya existe** se suma a un local adicional (Kevin trabaja en dos locales, §4.6). Para el alta desde cero, ver `POST /locales/{local}/profesionales` arriba.

### `GET /locales/{local}/asignaciones` 🔒 · `POST /locales/{local}/asignaciones` 🔒 · `PATCH /asignaciones/{asignacion}` 🔒

**Body de creación**: `{ "profesional_id": "uuid", "rol": "barbero", "modalidad": "renta_silla", "comision_pct": 60, "desde": "2026-09-01" }` (`desde` opcional, hoy si se omite). `PATCH` acepta `rol`/`modalidad`/`comision_pct`, todos opcionales.

**201 / 200**:
```json
{ "id": "uuid", "local_id": "uuid", "profesional_id": "uuid", "profesional_nombre": "Kevin Ruiz",
  "rol": "barbero", "modalidad": "renta_silla", "comision_pct": 60, "desde": "2026-09-01", "hasta": null }
```

### `POST /asignaciones/{asignacion}/terminar` 🔒

Pone `hasta` en vez de borrar la fila — el vínculo laboral queda en el historial. **Body**: `{ "hasta": "2026-09-15" }` (opcional, hoy si se omite). **200**: mismo shape que arriba, con `hasta` ya lleno.

---

## Staffing — Turnos (horario recurrente)

### `GET /asignaciones/{asignacion}/turnos` 🔒 · `POST /asignaciones/{asignacion}/turnos` 🔒

Horario semanal recurrente de esa asignación (§4.6). Postgres es la única fuente de verdad contra traslapes — un profesional no puede tener dos turnos encimados aunque sean de **locales distintos** (§4.11): es un solo cuerpo.

**Body de creación**: `{ "dia_semana": 1, "entra": "09:00", "sale": "18:00", "vigente_desde": "2026-09-15", "vigente_hasta": null }`. `dia_semana` 0-6 (0 = domingo). `vigente_hasta` opcional (`null` = sigue vigente).

**201 / 200**:
```json
{ "id": "uuid", "asignacion_id": "uuid", "profesional_id": "uuid", "local_id": "uuid",
  "dia_semana": 1, "entra": "09:00", "sale": "18:00", "vigente_desde": "2026-09-15", "vigente_hasta": null }
```

**422** si se traslapa con otro turno del mismo profesional (campo `entra`): `"Ese horario se traslapa con otro turno de este profesional (en este local o en otro)."`. También **422** si `sale` no es posterior a `entra` (campo `sale`).

### `PATCH /turnos/{turno}` 🔒 · `DELETE /turnos/{turno}` 🔒

`PATCH` acepta cualquier campo de la creación, todos opcionales (mismas validaciones de traslape). `DELETE` borra de verdad (sin `activo`/`estado`) — **204**, sin cuerpo.

---

## Staffing — Recursos del local

### `GET /locales/{local}/recursos` 🔒 · `POST /locales/{local}/recursos` 🔒

Sillas, mesas, tinas: una fila por unidad física (§4.6) — nunca una fila con cantidad.

**Body de creación**: `{ "tipo": "silla", "nombre": "Silla 3" }`. `tipo` ∈ `silla | mesa_unas | lavacabezas | tina | box_privado`.

**201 / 200**: `{ "id": "uuid", "local_id": "uuid", "tipo": "silla", "nombre": "Silla 3", "activo": true }`

### `PATCH /recursos/{recurso}` 🔒 · `DELETE /recursos/{recurso}` 🔒

`PATCH` acepta `tipo`/`nombre`, opcionales. `DELETE` **no borra la fila**: pone `activo: false` (§4.2) — marcar una unidad fuera de servicio no afecta las demás. **204**, sin cuerpo.

---

## Staffing — Habilidades

### `GET /profesionales/{profesional}/habilidades` 🔒 · `POST /profesionales/{profesional}/habilidades` 🔒

Qué servicios del local puede atender el profesional (§4.6) — la tabla que evita asignar uñas al barbero que solo hace fades.

**Body de creación**: `{ "servicio_local_id": "uuid", "precio_override": null }`. `servicio_local_id` debe existir. `precio_override` opcional (el profesional cobra distinto al precio base del servicio — un senior, por ejemplo).

**201 / 200**: `{ "id": "uuid", "profesional_id": "uuid", "servicio_local_id": "uuid", "servicio_nombre": "Corte fade", "precio_override": null }`

Un mismo profesional no puede tener la misma habilidad dos veces (**422**, campo `servicio_local_id`).

### `DELETE /profesionales/{profesional}/habilidades/{habilidad}` 🔒

Sin `activo`/`estado`: se borra de verdad. **204**, sin cuerpo.

---

## Staffing — Overrides por fecha (turno-fechas)

### `GET /profesionales/{profesional}/turno-fechas` 🔒 · `POST /profesionales/{profesional}/turno-fechas` 🔒

Excepción puntual sobre el turno recurrente para un día concreto ("este sábado Kevin no va a Urdesa, va a Alborada") — no modifica el turno base (§4.6).

**Body de creación**: `{ "local_id": "uuid", "fecha": "2026-09-20", "tipo": "extra", "entra": "10:00", "sale": "16:00", "nota": null }`. `tipo` ∈ `extra | reemplaza | cancela`. `entra`/`sale` **obligatorios si `tipo` no es `cancela`**, y deben ir `null` cuando sí lo es (**422**, campo `entra`, si no calzan).

**201 / 200**: `{ "id": "uuid", "profesional_id": "uuid", "local_id": "uuid", "fecha": "2026-09-20", "tipo": "extra", "entra": "10:00", "sale": "16:00", "nota": null }`

### `DELETE /profesionales/{profesional}/turno-fechas/{turnoFecha}` 🔒

Borra de verdad. **204**, sin cuerpo.

---

## Staffing — Excepciones

Cierres y ausencias (§4.6): de un **local** (feriado, remodelación), de un **profesional** (vacaciones, enfermedad — bloquea TODOS sus locales, no solo uno) o de un **recurso** (mantenimiento). Tres orígenes de creación/listado, un solo `DELETE`.

### `GET /locales/{local}/excepciones` 🔒 · `POST /locales/{local}/excepciones` 🔒
### `GET /profesionales/{profesional}/excepciones` 🔒 · `POST /profesionales/{profesional}/excepciones` 🔒
### `GET /recursos/{recurso}/excepciones` 🔒 · `POST /recursos/{recurso}/excepciones` 🔒

**Body de creación** (igual en los tres): `{ "fecha_inicio": "2026-12-25T00:00:00Z", "fecha_fin": "2026-12-26T00:00:00Z", "motivo": "feriado", "nota": null }`. `motivo` ∈ `feriado | vacaciones | mantenimiento | personal | bloqueo_manual`. `fecha_fin` debe ser posterior a `fecha_inicio`.

**201 / 200**: `{ "id": "uuid", "local_id": "uuid"|null, "profesional_id": "uuid"|null, "recurso_id": "uuid"|null, "fecha_inicio": "...", "fecha_fin": "...", "motivo": "feriado", "nota": null }` — solo uno de `local_id`/`profesional_id`/`recurso_id` viene lleno, según el origen usado.

### `DELETE /excepciones/{excepcion}` 🔒

Autoriza contra el dueño real de la excepción (local, profesional o recurso, según cuál esté lleno). **204**, sin cuerpo.

---

## Scheduling — Disponibilidad

### `GET /locales/{local}/disponibilidad`

**Público**, sin autenticación — el cliente necesita ver horarios antes de tener cuenta. Motor de slots (§5): aplica los 10 filtros (horario del local, turno del profesional con overrides de `turno_fecha`, habilidad, excepciones, citas existentes en cualquier local con `traslado_min` si es de otro local, recurso libre, anticipación mínima, horizonte máximo). Candidatos cada 15 minutos.

**Query**: `?fecha=2026-09-22&servicios[]=uuid&servicios[]=uuid&profesional_id=uuid` — `fecha` y `servicios` obligatorios (al menos un `servicio_local_id`); `profesional_id` opcional, para pedir la disponibilidad de un profesional concreto en vez de todos los elegibles.

**200**:
```json
[
  { "profesional_id": "uuid", "recurso_id": "uuid"|null, "inicio": "2026-09-22T14:00:00Z", "fin": "2026-09-22T14:30:00Z" }
]
```
`recurso_id` solo viene lleno si el servicio pedido necesita un tipo de recurso concreto (`catalogo_servicio.tipo_recurso` distinto de `ninguno`) y hay uno libre en esa ventana — si no hay ninguno libre, esa combinación de horario simplemente no aparece en la lista.

La disponibilidad se cachea 15 minutos por (local, profesional, día) y se invalida por evento (turno o excepción modificada), nunca solo por TTL — cancelar algo en Staffing libera el slot ya, no en 15 minutos.

---

## Scheduling — Citas

Máquina de estados completa (§6):
```
reservada ──confirma──> confirmada ──llega──> en_curso ──termina──> completada
    │                        │                                          │
    │ expira                 │ cancela_cliente / cancela_local          └──> resena
    ▼                        │ no_show
 expirada                    │ reagenda
                             ▼
                  cancelada_* / no_show / reagendada
```
`completada`, `cancelada_cliente`, `cancelada_local`, `no_show`, `expirada` y `reagendada` son terminales. Cada transición queda auditada en `cita_evento` (quién, cuándo, de qué estado a cuál).

### `GET /locales/{local}/citas` 🔒 · `GET /citas/{cita}` 🔒

`index`: cualquier miembro con rol en el local (propietario, admin o recepción) — la agenda del local. Filtros de query opcionales: `?fecha=2026-09-22&profesional_id=uuid&estado=confirmada`. `show`: el mismo staff, **o el cliente dueño de la cita**.

**200** (`show`, y cada elemento del array de `index`):
```json
{ "id": "uuid", "local_id": "uuid", "profesional_id": "uuid", "recurso_id": "uuid"|null,
  "cliente_id": "uuid", "cliente_telefono": null, "inicio": "...", "fin": "...", "estado": "confirmada", "canal": "app",
  "precio_total": "25.00", "propina": "0.00", "metodo_pago": null, "cliente_nuevo": true,
  "para_tipo": "titular", "para_nombre": null, "mascota_id": null, "nota_cliente": null,
  "codigo": "AB12CD34", "reagendada_de_id": null,
  "expira_at": null, "confirmada_at": "...", "cancelada_at": null, "completada_at": null,
  "items": [{ "id": "uuid", "servicio_local_id": "uuid", "precio": "25.00", "duracion_min": 30, "comisionable": true, "comision_pct": "50.00" }],
  "productos": [] }
```
`items`/`productos` llevan precio y comisión **congelados** al momento de agendar/atender (§4.7) — no se recalculan si el local cambia precios después. `cliente_telefono` (§3.3) viene `null` salvo que `estado` sea `confirmada`, `en_curso` o `completada` — el local no necesita el teléfono de un hold que puede expirar sin más.

### `GET /mis-citas` 🔒

Historial del cliente autenticado (§7.6): cruza **todos** los locales que visitó, sin scoping a uno — es un dato del cliente, no de un local.

**Query opcional**: `?estado=completada&local_id=uuid`.

**200**: array con el mismo shape de `show`, ordenado por `inicio` descendente.

### `POST /locales/{local}/citas` 🔒 · `Idempotency-Key` obligatorio

Crea el *hold* (§5.4): `reservada` con `expira_at = +10 min` — salvo que el cliente tenga 3+ no-shows (`cliente_perfil.requiere_confirmacion`), en cuyo caso nace `confirmada` directo, sin el respiro del hold. Camino optimista (§5.3): si dos clientes piden el mismo horario a la vez, Postgres decide con el constraint `EXCLUDE` — el que pierde recibe `409`.

**Body**:
```json
{ "profesional_id": "uuid", "servicios": ["uuid"], "inicio": "2026-09-22T14:00:00Z",
  "recurso_id": "uuid", "para_tipo": "titular", "para_nombre": null, "mascota_id": null, "nota_cliente": null }
```
`servicios` (mínimo uno) e `inicio` normalmente salen de un slot de `GET /locales/{local}/disponibilidad`. `recurso_id`/`para_tipo`/`para_nombre`/`mascota_id`/`nota_cliente` opcionales. `para_tipo` ∈ `titular | otra_persona | mascota`; `para_nombre` obligatorio si es `otra_persona`, `mascota_id` obligatorio si es `mascota`.

**201**: mismo shape que `show`. **409** (`slot_ya_ocupado`) si el horario se ocupó justo antes.

### `POST /locales/{local}/citas/walk-in` 🔒 · `Idempotency-Key` obligatorio

Solo propietario/admin/recepción. Un cliente que llega sin cita: ocupa slot igual que uno de la app (mismo constraint `EXCLUDE`), pero nace **`confirmada`** directo y `canal: "local"` — no hay hold que confirmar, el cliente ya está ahí.

**Body**: igual que crear, más `cliente_id` (si ya es cliente) **o** `nombre`+`telefono` (crea un usuario mínimo sin contraseña si el teléfono no existe todavía — no es el flujo completo de registro, solo lo necesario para que la cita tenga dueño).

**201**: mismo shape que `show`, con `estado: "confirmada"` y `canal: "local"`.

### `POST /citas/{cita}/confirmar` 🔒

El cliente dueño del hold, o el staff del local. `reservada → confirmada`. **200**: la cita actualizada. **422** si no estaba en `reservada`.

### `POST /citas/{cita}/iniciar` 🔒

Solo staff — "el cliente llegó". `confirmada → en_curso`. **200** / **422** si no estaba `confirmada`.

### `POST /citas/{cita}/completar` 🔒

Solo staff. `en_curso → completada` — el único estado que habilita reseña y cuenta para ranking/liquidación (§6). Actualiza `cliente_local` (total de visitas, primera/última cita).

**Body**: `{ "propina": 5.00 }` (opcional — 100% al profesional, **no** es base de comisión).

**200** / **422** si no estaba `en_curso`.

### `POST /citas/{cita}/cancelar` 🔒 · `Idempotency-Key` obligatorio

El cliente dueño, o el staff del local. `reservada|confirmada|en_curso → cancelada_cliente` (si cancela el cliente) o `cancelada_local` (si cancela el staff). Si el cliente cancela dentro de la ventana de `local.politica_cancelacion_horas`, cuenta como tardía: se incrementa `cliente_perfil.cancelaciones_tardias` (no cambia el `estado`, solo el contador — igual que "3 no-shows", es historial interno, nunca puntaje público). Libera el slot para la lista de espera (§ abajo).

**Body**: `{ "motivo": "..." }` (opcional). **200** / **422** si la cita ya está en un estado terminal.

### `POST /citas/{cita}/no-show` 🔒

Solo staff. `confirmada|en_curso → no_show`. Incrementa `cliente_perfil.no_shows`; al llegar a 3, activa `requiere_confirmacion` (la próxima reserva de ese cliente nace `confirmada` directo, sin hold). Libera el slot para la lista de espera. **200** / **422** si no aplica.

### `POST /citas/{cita}/reagendar` 🔒

El cliente dueño, o el staff. Reagendar **no es** cancelar + crear (§4.7): la cita vieja pasa a `reagendada` (terminal, no penaliza — no toca `cancelaciones_tardias` ni `no_shows`) y se crea una cita **nueva**, enlazada por `reagendada_de_id`, con el mismo camino optimista que agendar de cero.

**Body**: igual que crear una cita (`profesional_id`, `servicios`, `inicio`, `recurso_id` opcional).

**201**: la cita **nueva**, con `reagendada_de_id` apuntando a la vieja. **422** si la vieja ya está en un estado terminal. **409** si el nuevo horario se ocupó justo antes.

### `POST /citas/{cita}/productos` 🔒

Solo staff. Agrega una línea de producto (pomada, cera, shampoo) a una cita ya agendada — precio y comisión **congelados** desde `Producto` en ese momento, y suma a `cita.precio_total`.

**Body**: `{ "producto_id": "uuid", "cantidad": 1 }` (`cantidad` opcional, 1 si se omite).

**200**: la cita con el `cita_producto` nuevo en `productos`.

---

## Scheduling — Lista de espera

Cuesta poco, retiene mucho: convierte cancelaciones en citas (§4.7).

### `GET /locales/{local}/esperas` 🔒 · `POST /locales/{local}/esperas` 🔒

`GET`: cualquier miembro del local (para saber a quién llamar cuando se libera un cupo). `POST`: cualquier cliente autenticado, para anotarse.

**Body de creación**: `{ "servicio_local_id": "uuid", "fecha_deseada": "2026-09-22", "profesional_id": "uuid", "desde": "10:00", "hasta": "14:00" }` — solo `servicio_local_id`/`fecha_deseada` obligatorios; `profesional_id` opcional (si no importa cuál); `desde`/`hasta` opcionales (ventana horaria preferida).

**201 / 200**:
```json
[{ "id": "uuid", "local_id": "uuid", "cliente_id": "uuid", "profesional_id": "uuid"|null,
   "servicio_local_id": "uuid", "fecha_deseada": "2026-09-22", "desde": "10:00", "hasta": "14:00", "estado": "activa" }]
```
`estado` pasa a `notificada` automáticamente cuando se cancela/no-show/expira una cita que calza (mismo local, misma fecha, mismo servicio, y el mismo profesional si se pidió uno concreto), y a `convertida` cuando ese cliente sí reserva. Sin auto-reserva: el cliente sigue agendando por el flujo normal — esto solo decide a quién avisar primero (para cuando exista el módulo Notifications).

---

## Reviews — Reseñas y reportes

Reglas duras (§4.8): solo se puede reseñar una cita `completada`, dentro de los 14 días siguientes, y una sola vez por cita (`Cita::admiteResena()`).

### `POST /citas/{cita}/resenas` 🔒

Solo el cliente dueño de la cita.

**Body**:
```json
{ "puntaje_local": 5, "puntaje_profesional": 4, "puntualidad": 5, "limpieza": 5, "comentario": "Excelente atención" }
```
`puntaje_local` obligatorio (1-5). `puntaje_profesional`/`puntualidad`/`limpieza`/`comentario` opcionales.

**201**:
```json
{ "id": "uuid", "cita_id": "uuid", "local_id": "uuid", "profesional_id": "uuid",
  "puntaje_local": 5, "puntaje_profesional": 4, "puntualidad": 5, "limpieza": 5,
  "comentario": "Excelente atención", "estado": "publicada",
  "respuesta_local": null, "respuesta_at": null, "created_at": "..." }
```

**422** (campo `cita_id`) si la cita no está `completada`, si pasó la ventana de 14 días, o si ya tiene una reseña.

### `GET /locales/{local}/resenas` 🔒

Cualquier miembro con rol en el local — a diferencia del perfil público (`GET /locales/{local}/perfil-publico`, Fase 7), aquí se ven **todos** los `estado` (`publicada`, `en_revision`, `oculta`), no solo las publicadas.

**200**: array con el mismo shape que la creación.

### `POST /resenas/{resena}/responder` 🔒

Solo propietario/admin del local (§3.2, misma regla que editar catálogo).

**Body**: `{ "respuesta_local": "Gracias por tu visita, te esperamos pronto" }`.

**200**: la reseña con `respuesta_local`/`respuesta_at` ya llenos.

**Sin endpoint para cambiar `estado`** (moderación de `en_revision`/`oculta`) todavía: no existe panel de soporte de plataforma — se revisa a mano, mismo precedente que las solicitudes al catálogo maestro (Fase 3).

### `POST /reportes` 🔒

Cualquier usuario autenticado reporta una reseña, foto, local o profesional (§4.8) — polimórfico sin FK a propósito, no valida que `objeto_id` exista.

**Body**:
```json
{ "tipo": "resena", "objeto_id": "uuid", "motivo": "spam", "detalle": "Comentario repetido" }
```
`tipo` ∈ `resena | foto | local | profesional`. `motivo` ∈ `difamacion | contenido_inapropiado | falso | spam | otro`. `detalle` opcional.

**201**:
```json
{ "id": "uuid", "tipo": "resena", "objeto_id": "uuid", "reportante_id": "uuid",
  "motivo": "spam", "detalle": "Comentario repetido", "estado": "pendiente" }
```

**Sin endpoint de resolver/descartar** todavía — mismo motivo que arriba.

---

## Notifications — Dispositivos y preferencias

El envío real (push/WhatsApp/WebSocket) todavía no está conectado a ningún proveedor — no hay proyecto de Firebase, ni plantillas de WhatsApp aprobadas por Meta, ni el paquete `laravel/reverb` instalado (bloqueadores externos, ver `context/plan-implementacion.md`). Toda la lógica de negocio (anti-duplicados, preferencias, ventana de silencio, cancelar-y-reprogramar) ya funciona de punta a punta; lo único pendiente es el "último tramo" del envío, igual que pasaba con el OTP hasta esta fase.

### `POST /dispositivos` 🔒

Registra o actualiza (upsert por `token`) el dispositivo del usuario autenticado — llamar al iniciar sesión, al aceptar el permiso de notificaciones, en `onTokenRefresh` y al reinstalar (§11.7).

**Body**:
```json
{ "token": "fcm-token...", "plataforma": "android", "apns_token": null, "app_version": "1.2.0" }
```
`plataforma` ∈ `android | ios`. `apns_token`/`app_version` opcionales.

**201**:
```json
{ "id": "uuid", "plataforma": "android", "app_version": "1.2.0", "activo": true, "ultimo_uso_at": "..." }
```

### `DELETE /dispositivos/{deviceToken}` 🔒

Solo el dueño del token. Llamar **al cerrar sesión** (§11.7) — si no, el dueño anterior del teléfono sigue recibiendo las citas de otra persona. **204**, sin cuerpo.

### `GET /mis-preferencias-notificacion` 🔒

Una fila por cada categoría activa de la plataforma, con `push`/`whatsapp` en `true` por defecto si el usuario nunca la tocó.

**200**:
```json
[
  { "categoria": "citas", "push": true, "whatsapp": true },
  { "categoria": "agenda", "push": true, "whatsapp": true },
  { "categoria": "social", "push": true, "whatsapp": true },
  { "categoria": "promos", "push": true, "whatsapp": false }
]
```

### `PUT /mis-preferencias-notificacion` 🔒

Reemplaza las categorías enviadas (las que no se mandan quedan como estaban — a diferencia de la sincronización de amenidades, aquí no hace falta mandar el conjunto completo).

**Body**:
```json
{ "preferencias": [{ "categoria": "promos", "push": true, "whatsapp": false }] }
```

**200**: el listado completo actualizado, mismo shape que el `GET`.

**Nota de negocio (§11)**: apagar una categoría no bloquea lo transaccional indispensable de esa misma categoría (la cita creada, confirmada o cancelada le llega igual al cliente) — esa excepción la aplica el servidor, no es configurable desde el cliente.

---

## Billing — Suscripción, cobros y liquidación

**Fuera de la v1 como función cobrada** (§15.2, §9.7: "todo gratis los primeros ~6 meses") — pero el código ya existe y funciona de punta a punta. No hay pasarela de pago conectada (§10.1: "en la v1 nada de dinero pasa por la plataforma") ni proveedor de facturación electrónica del SRI — `POST /cobros/{cobro}/marcar-pagado` es un registro administrativo (el cobro real ocurre por fuera, transferencia o el medio que sea), y `comprobante_sri` se emite contra un puerto provisional, igual que el push/WhatsApp de la sección anterior.

Todas las rutas de esta sección son 🔒, y solo para quien administra dinero: `NegocioPolicy::gestionarSuscripcion` (el dueño legal del negocio, `negocio.propietario_id` — ni siquiera `admin`) para suscripción/cobros, `LiquidacionPolicy` (propietario/admin, nunca recepción) para liquidaciones.

### `POST /negocios/{negocio}/suscripcion`

Activa (o reemplaza) la suscripción del negocio. Actualiza `negocio.plan_id`/`plan_vigente_hasta` en la misma operación.

**Body**: `{ "plan": "pro", "profesionales": 4, "ciclo": "mensual" }`. `plan` es el `codigo` de un plan activo. Precio (§9.6): `$8 + $5 × (profesionales - 1)`, mensual; anual paga 10 meses, 2 gratis.

**201**: `{ "id": "uuid", "negocio_id": "uuid", "plan": "pro", "profesionales": 4, "precio_mensual": "23.00", "ciclo": "mensual", "estado": "activa", "vigente_hasta": "2026-10-16" }`

### `GET /negocios/{negocio}/suscripcion`

La suscripción más reciente del negocio. Mismo shape que la creación.

### `POST /suscripciones/{suscripcion}/cancelar`

Cancela de inmediato (`estado: "cancelada"`) y el negocio vuelve a `free` ya mismo — la v1 no modela periodo de gracia por falta de cobro real. Sin body.

### `GET /negocios/{negocio}/cobros`

Historial de cobros de todas las suscripciones del negocio.

**200**: `[{ "id": "uuid", "suscripcion_id": "uuid", "monto": "23.00", "estado": "pendiente", "intentos": 0, "pagado_at": null, "comprobante_sri": null }]`

### `POST /cobros/{cobro}/marcar-pagado`

Registro administrativo (no hay pasarela real, §10.1). Pone `estado: "pagado"`, `pagado_at`, y emite `comprobante_sri` a través del puerto de facturación electrónica. Sin body.

### `GET /locales/{local}/liquidaciones` · `POST /locales/{local}/liquidaciones`

`GET`: historial del local. `POST`: genera o **regenera** el borrador de un periodo — solo mientras siga en `borrador`; contra una liquidación ya `cerrada`/`pagada` del mismo periodo responde **422**.

**Body de creación**: `{ "profesional_id": "uuid", "periodo_desde": "2026-09-01", "periodo_hasta": "2026-09-30" }`.

**201 / 200**:
```json
{
  "id": "uuid", "local_id": "uuid", "profesional_id": "uuid",
  "periodo_desde": "2026-09-01", "periodo_hasta": "2026-09-30",
  "total_servicios": "120.00", "total_productos": "20.00",
  "comision_servicios": "50.00", "comision_productos": "2.00",
  "total_propinas": "15.00", "total_a_pagar": "67.00",
  "estado": "borrador", "cerrada_at": null
}
```
`total_servicios`/`total_productos`/`comision_servicios`/`comision_productos` **solo aparecen si el negocio es plan Pro** (§9.4-9.5: en Free "se ve el total, no el desglose") — ausentes del todo en la respuesta si no, nunca `null`. `total_propinas`/`total_a_pagar`/`estado` siempre visibles en ambos planes. Los montos salen del precio y la comisión ya **congelados** en `cita_item`/`cita_producto` al agendar/atender — nunca se recalculan contra el precio actual del servicio. La propina se suma completa a `total_a_pagar`, nunca multiplicada por `comision_pct` (§4.7).

### `POST /liquidaciones/{liquidacion}/cerrar`

Solo desde `borrador`. Recalcula una última vez (por si algo cambió desde el último borrador) y pasa a `cerrada` con `cerrada_at` — desde ahí ya no se puede regenerar ni volver a cerrar. **422** si no estaba en `borrador`.

### `POST /liquidaciones/{liquidacion}/marcar-pagada`

Solo desde `cerrada` → `pagada`. **422** si no estaba `cerrada`.

---

## Símbolos

🔒 requiere `Authorization: Bearer <token>`.

---

## Pendiente de documentar aquí

Todos los módulos del §12.3 tienen su contrato documentado arriba. Lo que falta es infraestructura de producción (Fase 11: Octane, Horizon, Sentry, PgBouncer, despliegue), no endpoints nuevos — ver `context/plan-implementacion.md`.
