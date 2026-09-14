# FullPinta — Especificación técnica y de producto

Marketplace de descubrimiento y agendamiento para barberías, gabinetes de estética y grooming de mascotas. Mercado inicial: Guayaquil, Ecuador.

Documento de trabajo. Todo lo referido a normativa, tarifas de terceros y criterios legales es referencial y debe confirmarse con el responsable competente.

---

## Índice

1. [Resumen del producto](#1-resumen-del-producto)
2. [Decisiones de producto](#2-decisiones-de-producto)
3. [Modelo de dominio](#3-modelo-de-dominio)
4. [Especificación de base de datos](#4-especificación-de-base-de-datos)
5. [Motor de disponibilidad y slots](#5-motor-de-disponibilidad-y-slots)
6. [Máquina de estados de la cita](#6-máquina-de-estados-de-la-cita)
7. [Reputación y ranking](#7-reputación-y-ranking)
8. [Búsqueda](#8-búsqueda)
9. [Monetización](#9-monetización)
10. [Pagos y transacciones](#10-pagos-y-transacciones)
11. [Notificaciones](#11-notificaciones)
12. [Arquitectura técnica](#12-arquitectura-técnica)
13. [Cumplimiento legal](#13-cumplimiento-legal)
14. [Métricas](#14-métricas)
15. [Alcance v1](#15-alcance-v1)
16. [Decisiones pendientes](#16-decisiones-pendientes)
17. [Antes de programar](#17-antes-de-programar)

---

## 1. Resumen del producto

Tres tipos de actor y un objeto central.

- **Cliente**: busca locales cerca, compara precios y reseñas, agenda, reseña.
- **Profesional** (barbero, estilista, manicurista, groomer): atiende, gestiona su agenda, ve sus comisiones.
- **Negocio** (dueño, admin, recepción): administra locales, precios, personal y suscripción.

El objeto central es la **cita**. Todo el valor —reputación, comisiones, métricas, monetización— se deriva de citas cumplidas.

**El riesgo principal del proyecto no es técnico.** Es el arranque de dos lados: se necesitan locales para atraer clientes y clientes para retener locales. La estrategia de mitigación (zona única, todo gratis al inicio, herramienta de gestión como ancla) está en §9 y §15.

---

## 2. Decisiones de producto

Las reglas que sostienen el diseño. Cambiar una de estas cambia el esquema.

| Decisión | Regla |
|---|---|
| Citas | **Ilimitadas en todos los planes.** Nunca se limita volumen. |
| Monetización | Suscripción por negocio, por funciones y por número de profesionales. Sin comisión por cita en v1. |
| Pagos | **No pasa dinero por la plataforma en v1.** Ver §10. |
| Verificado | Se gana (validación de local y RUC). **No se vende.** |
| Posicionamiento pagado | Existe, en bloque separado y etiquetado. El ranking orgánico no se compra. |
| Reputación | Por local y por profesional, separadas. Nunca promediada por marca. |
| Roles | No excluyentes. El dueño también corta pelo; el barbero es cliente de otra barbería. |
| Catálogo de servicios | Maestro y cerrado, definido por la plataforma. Nunca texto libre. |
| Amenidades | Catálogo cerrado. Sin filtro no sirven. |
| Lanzamiento | Una sola zona geográfica. Todo gratis los primeros ~6 meses. |
| Métrica de supervivencia | % de citas que son de cliente nuevo para ese local. |

---

## 3. Modelo de dominio

### 3.1 Las cinco entidades que hay que separar bien

**Negocio ≠ Local.** El negocio es la marca (dueño, RUC, suscripción). El local es la sucursal física con coordenadas, horarios y reputación propia. Un negocio con 3 sucursales tiene 3 locales.

La reseña y la valoración van al **local**, no al negocio. Si la sucursal de la Alborada atiende bien y la de Urdesa mal, el usuario tiene que verlo separado. La suscripción se cobra al negocio: un dueño no paga 3 planes.

**Profesional ≠ Asignación.** El profesional es una persona con identidad propia, independiente del local. La asignación es su vínculo laboral con un local, con fechas y porcentaje de comisión. Un profesional puede tener varias asignaciones vigentes a la vez.

**Asignación ≠ Turno.** La asignación es el vínculo; los turnos son los bloques horarios. Un mismo vínculo puede tener varios bloques por día y variar por día de la semana.

**Usuario ≠ Rol.** El usuario es una identidad (teléfono, nombre). Los roles salen de sus relaciones: perfil de cliente (siempre existe), profesional (si atiende), miembro de negocio (si administra).

**Catálogo ≠ Servicio del local.** El catálogo es maestro y lo define la plataforma; el servicio del local es el catálogo más precio y duración de ese local.

### 3.2 Los cuatro roles

Son cuatro, no tres. **Recepción** es un rol aparte: en locales medianos hay alguien en el mostrador que agenda y cobra pero no debe ver las comisiones de los barberos. Sin ese rol, el dueño le da su propia clave a la recepcionista y se pierde el control de acceso.

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

En la app: un solo proyecto Flutter con selector de contexto al entrar si el usuario tiene más de un rol. No dos apps.

### 3.3 Reglas de privacidad entre actores

| Situación | Regla |
|---|---|
| Local A consulta disponibilidad de un profesional que también trabaja en local B | Ve el bloque como `ocupado / otro compromiso`. Sin local, sin cliente, sin servicio. Si el dueño ve que su barbero atiende en la competencia, hay problema laboral y te sacan de la app. |
| Nota del local sobre el cliente | Es dato del local. El local A nunca ve la nota del local B. |
| Teléfono del cliente | Visible solo en citas `confirmada` o posterior. Sin exportación masiva. Log de accesos. |
| Perfil del profesional | Público y buscable (decisión abierta, §16), pero **sin** notificación automática a sus clientes cuando cambia de local. |
| Comisiones | Cada profesional ve solo las suyas. Recepción no ve ninguna. |

---

## 4. Especificación de base de datos

### 4.1 Motor y extensiones

**PostgreSQL 15 o superior. No negociable.** Es la única pieza del stack donde la elección tiene consecuencias técnicas reales:

- **PostGIS** — búsqueda por cercanía con índice espacial. Sin esto, "barberías cerca de mí" hace scan completo.
- **btree_gist** — permite usar columnas escalares (`uuid`, `smallint`) junto a rangos dentro de constraints `EXCLUDE USING gist`. Es lo que hace posible la detección de traslapes a nivel de base de datos.
- **Tipo `franja`** — Postgres no trae un tipo de rango para `time`. Hay que crearlo para detectar traslape de turnos.

```sql
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS btree_gist;

DO $do$
BEGIN
    CREATE TYPE franja AS RANGE (subtype = time);
EXCEPTION
    WHEN duplicate_object THEN NULL;
END
$do$;
```

### 4.2 Convenciones

| Aspecto | Convención |
|---|---|
| Claves primarias | `uuid`, generado en la aplicación. Evita filtrar volumen de negocio y facilita idempotencia en móvil. |
| Nombres de tabla | Singular, snake_case: `cita`, `servicio_local`. |
| Enums | `varchar` + `CHECK`, no tipos nativos de Postgres (los enums nativos son dolorosos de alterar). |
| Timestamps | `timestamptz` siempre. **Todo en UTC en base de datos**, conversión a `America/Guayaquil` en presentación. Ecuador no tiene horario de verano, pero no se construye la deuda. |
| Rangos | Siempre `'[)'` — inicio incluido, fin excluido. Una cita que termina 10:30 y otra que empieza 10:30 **no** se traslapan. Con `'[]'` se pierde un slot en cada frontera. |
| Borrado | Soft delete vía campo `estado` o `activo`. No se borran locales, profesionales ni citas. |
| Dinero | `numeric(10,2)`. Nunca float. |
| Precios y comisiones históricas | **Congelados** en la fila de la transacción. Ver §4.7. |

### 4.3 Módulo Identity

```
usuario
  id                    uuid PK
  telefono              varchar(20) UNIQUE      -- identificador principal, obligatorio SIEMPRE
  telefono_verificado   boolean DEFAULT false
  google_id             varchar UNIQUE NULL     -- `sub` del id_token de Google
  email                 varchar UNIQUE NULL
  nombre                varchar
  foto_url              varchar NULL
  password_hash         varchar NULL            -- NULL = cliente sombra
  anonimizado_at        timestamptz NULL        -- LOPDP: derecho de eliminación
  created_at, updated_at
```

En Ecuador el teléfono es mejor identificador que el email: más gente lo tiene consistente y sirve para WhatsApp. Es **obligatorio para toda cuenta**, sin importar por qué método se registró — verificado siempre por el mismo OTP.

**Tres métodos de login/registro, un solo teléfono y una sola verificación:**

- **Teléfono + OTP** (`POST /auth/otp/solicitar` + `verificar`): el original. Registro e inicio de sesión son el mismo endpoint.
- **Google** (`POST /auth/google`, `{id_token, telefono}`): el front pide el teléfono en el mismo formulario, porque Google no lo da. Si el `google_id` del token ya existe, es login. Si no, pero el email del token ya es de otra cuenta (creada por OTP o por correo), se **enlaza** `google_id` a esa cuenta — el token ya probó que la persona es dueña de ese correo. Si ninguno existe, se crea una cuenta nueva con `telefono_verificado: false`.
- **Correo + contraseña** (`POST /auth/registro` con `{nombre, email, password, telefono}`, `POST /auth/login` con `{email, password}`): a diferencia de Google, acá nunca se enlaza por coincidencia de correo — una contraseña elegida por cualquiera no debe poder tomar una cuenta ajena. Email y teléfono se validan como únicos al registrar.

En los tres, si la cuenta nace con `telefono_verificado: false` (Google o correo), se verifica con el **mismo** `POST /auth/otp/solicitar` + `POST /auth/otp/verificar` de siempre — no hay un mecanismo de verificación aparte por método.

**Cliente sombra** (`password_hash IS NULL`): la recepción agenda un walk-in con solo nombre y teléfono. Cuando esa persona se registra en la app con el mismo teléfono (por cualquiera de los tres métodos), **reclama** el registro y hereda su historial. Sin esto pasa una de dos cosas, ambas malas: los walk-ins no entran a la agenda y la disponibilidad miente, o entran duplicados y la métrica de cliente nuevo queda inservible. OTP y Google asignan un hash aleatorio e inutilizable a `password_hash` (nunca se autentica por él); solo el registro por correo guarda ahí el hash real de la contraseña elegida.

```
cliente_perfil
  id                      uuid PK
  usuario_id              uuid FK -> usuario UNIQUE
  genero                  enum(m, f, otro, no_decir) NULL
  fecha_nacimiento        date NULL
  no_shows                int DEFAULT 0
  cancelaciones_tardias   int DEFAULT 0
  requiere_confirmacion   boolean DEFAULT false
```

Confiabilidad del cliente: se registra pero **no se muestra como puntaje público al cliente** — es hostil y lo espanta. Uso interno: al 3er no-show se activa `requiere_confirmacion`.

`id` es uuid como toda tabla del esquema (regla del proyecto, sin excepción) aunque la relación con `usuario` sea 1:1 — `usuario_id` queda como columna UNIQUE normal en vez de ser la propia PK.

```
tamano_mascota
  id       uuid PK
  codigo   varchar(20) UNIQUE   -- muy_pequeno, pequeno, mediano, grande, gigante
  nombre   varchar
  orden    smallint DEFAULT 0
  activo   boolean

mascota
  id             uuid PK
  usuario_id     uuid FK -> usuario
  nombre         varchar
  especie        enum(perro, gato, otro)
  raza           varchar NULL
  tamano_id      uuid FK -> tamano_mascota
  pelaje         enum(corto, medio, largo, rizado, doble_capa) NULL
  peso_kg        numeric(5,2) NULL
  temperamento   enum(tranquilo, nervioso, agresivo, desconocido) NULL
  nota           text NULL      -- "no tolera secadora"
  activo         boolean
```

`tamano_mascota` es tabla de parámetros compartida: también la referencia `servicio_local_tamano` (§4.5) — antes era el mismo `varchar`+`CHECK` repetido en las dos tablas.

```
favorito
  id             uuid PK
  usuario_id     uuid FK
  local_id       uuid FK NULL
  profesional_id uuid FK NULL

consentimiento
  id                  uuid PK
  usuario_id          uuid FK -> usuario
  finalidad           enum(operacion_servicio, comunicaciones_transaccionales,
                           marketing, transferencia_internacional)
  documento_version   varchar(20)
  otorgado            boolean
  origen              enum(app, local, web)
  ip                  varchar(45) NULL
  user_agent          varchar NULL
  otorgado_at         timestamptz
  revocado_at         timestamptz NULL
```

El consentimiento se registra **por finalidad**, con versión y timestamp, y debe poder probarse. Operar la cita no es lo mismo que recibir promociones. Ver §13.

### 4.4 Módulo Directory

```
plan
  id       uuid PK
  codigo   varchar(10) UNIQUE   -- free, pro
  nombre   varchar
  activo   boolean

negocio
  id                   uuid PK
  nombre_marca         varchar
  ruc                  varchar(13) NULL
  propietario_id       uuid FK -> usuario
  plan_id              uuid FK -> plan
  plan_vigente_hasta   date NULL

local
  id                          uuid PK
  negocio_id                  uuid FK -> negocio
  nombre                      varchar          -- "Sucursal Alborada"
  direccion                   text
  referencia                  text NULL        -- "diagonal al parque"
  ubicacion                   geography(Point, 4326)
  telefono                    varchar(20) NULL
  whatsapp                    varchar(20) NULL
  verificado                  boolean DEFAULT false
  verificado_at               timestamptz NULL
  estado                      enum(borrador, activo, pausado, suspendido)
  lead_time_min               smallint DEFAULT 60    -- anticipación mínima
  horizonte_dias              smallint DEFAULT 30    -- máximo a futuro
  politica_cancelacion_horas  smallint DEFAULT 2
  score_ranking               numeric(8,4) DEFAULT 0 -- job nocturno, nunca request
```

`plan` es tabla de parámetros compartida: también la referencia `suscripcion` (§4.10) — antes era el mismo `varchar`+`CHECK` repetido en las dos tablas.

```sql
CREATE INDEX local_ubicacion_gist ON local USING gist (ubicacion);
```

```
horario_local
  id           uuid PK
  local_id     uuid FK -> local
  dia_semana   smallint CHECK (0..6)    -- 0=domingo
  abre         time
  cierra       time
  CHECK (cierra > abre)
```

Varias filas por día permiten partir jornada (mañana/tarde).

```
amenidad_categoria
  id       uuid PK
  codigo   varchar(20) UNIQUE   -- confort, entretenimiento, ninos, accesibilidad, pago, politica
  nombre   varchar
  icono    varchar(60) NULL
  orden    smallint DEFAULT 0
  activo   boolean

amenidad
  id            uuid PK
  codigo        varchar(60) UNIQUE
  categoria_id  uuid FK -> amenidad_categoria
  nombre        varchar
  icono         varchar(60) NULL
  activo        boolean

local_amenidad
  local_id     uuid FK
  amenidad_id  uuid FK
  detalle      varchar NULL   -- "cerveza artesanal", "PS5"
  PK (local_id, amenidad_id)
```

**Por qué `categoria` es tabla y no un `enum` de texto (desviación de una versión anterior de este documento).** Un `varchar` + `CHECK` para la categoría de amenidad se repetía tal cual en cada fila de `amenidad`; promoverlo a tabla propia evita repetir el mismo string y permite referenciar por `id`, igual que `servicio_categoria` (§4.5). La regla general del proyecto es: si un valor se repite como dato en más de una fila y tiene atributos propios (nombre, ícono, orden), es tabla y se referencia por id — no un `varchar` suelto repetido.

Semilla del catálogo de amenidades:

- **confort**: aire acondicionado, sala de espera, bebidas, café, cerveza, wifi, música en vivo
- **entretenimiento**: TV, consola, mesa de billar, revistas
- **ninos**: área de niños, guardería, silla infantil
- **accesibilidad**: acceso silla de ruedas, baño accesible, parqueo, parqueo gratis
- **pago**: tarjeta, transferencia, Payphone, efectivo
- **politica**: atiende mujeres, atiende niños, acepta mascotas en sala, solo con cita, atiende sin cita

**Distinción crítica:** "acepta mascotas en sala" es una amenidad. "Baña perros" es un servicio de la vertical mascotas. Confundirlas lleva clientes con su perro a un local que solo lo deja entrar.

```
negocio_miembro
  id           uuid PK
  usuario_id   uuid FK -> usuario
  negocio_id   uuid FK -> negocio
  local_id     uuid FK -> local NULL   -- NULL = todos los locales
  rol          enum(propietario, admin, recepcion)
  desde        date
  hasta        date NULL

local_foto
  id         uuid PK
  local_id   uuid FK -> local
  url        varchar
  tipo       enum(fachada, interior, trabajo)
  orden      smallint
```

### 4.5 Módulo Catalog

```
vertical
  id       uuid PK
  codigo   varchar(20) UNIQUE   -- barberia, estetica, unas, mascotas
  nombre   varchar
  activo   boolean

servicio_categoria
  id           uuid PK
  vertical_id  uuid FK -> vertical
  codigo       varchar(40)       -- corte, barba, color, bano
  nombre       varchar           -- "Cortes"
  icono        varchar(60) NULL
  orden        smallint DEFAULT 0
  activo       boolean
  UNIQUE (vertical_id, codigo)

tipo_recurso
  id       uuid PK
  codigo   varchar(20) UNIQUE   -- silla, mesa_unas, lavacabezas, tina, box_privado, ninguno
  nombre   varchar
  activo   boolean

catalogo_servicio
  id                  uuid PK
  categoria_id        uuid FK -> servicio_categoria
  nombre              varchar          -- "Corte fade"
  slug                varchar(120) UNIQUE
  duracion_base_min   smallint
  tipo_recurso_id     uuid FK -> tipo_recurso
  activo              boolean
```

`tipo_recurso` es tabla de parámetros compartida con `recurso` (§4.6): antes eran dos `varchar`+`CHECK` sueltos con casi el mismo vocabulario y sin relación entre sí — nada garantizaba que el agendamiento pudiera casar un servicio que pide "silla" contra un recurso físico del mismo tipo. `ninguno` solo aplica aquí: un recurso físico siempre es algo concreto.

**Lo define la plataforma, no los locales.** Si cada local escribe sus servicios en texto libre, se termina con "corte caballero", "corte de cabello", "fade", "CORTE" y "corte + barba" como cosas distintas, y la búsqueda y el filtro de precios mueren. No hay vuelta atrás fácil de eso.

**Por qué `categoria` y `vertical` son tablas y no campos de texto.** Un `varchar` libre en una tabla que la plataforma controla termina con "corte", "cortes" y "Corte" conviviendo, y agrupa mal el perfil del local. Además el perfil agrupa los servicios por categoría, así que hacen falta `nombre` y `orden` en algún lado — en un campo de texto no caben. La regla del proyecto: si un dato se repite en más de una fila o en más de una tabla (como pasaba con `vertical`, repetido como `CHECK` en `servicio_categoria`, `catalogo_servicio` y `solicitud_catalogo`), se promueve a tabla propia y se referencia por `id` — nunca se repite el mismo `varchar` suelto en cada sitio que lo necesita. Son unas pocas filas, no un catálogo que crezca.

`codigo` en `servicio_categoria` es único solo junto a `vertical_id`, no por sí solo: el mismo código existe en verticales distintas — `corte` es categoría de barbería y también de estética, y no son la misma cosa. `catalogo_servicio` no guarda su propia vertical: se deriva de `categoria_id -> servicio_categoria.vertical_id`, así no hay dos columnas que puedan desincronizarse (un servicio con categoría de barbería pero vertical "estetica", por ejemplo) — la consistencia es estructural, no un constraint aparte que vigilarla.

Este documento normalizó `vertical` y `categoria` de amenidad en 2026-09-15 (antes vivían como `varchar` + `CHECK` en cada tabla que los usaba). `recurso_tipo`, `profesional_rol` y `mascota_tamano` se quedan como `varchar` + `CHECK` (§4.2) por ahora: no se repiten entre tablas ni tienen atributos propios (nombre, ícono, orden) — se revisan si eso cambia.

Semilla de categorías:

- **barberia**: corte, barba, cejas, tratamiento, color
- **estetica**: corte, peinado, color, tratamiento, depilacion
- **unas**: manicura, pedicura, esmaltado, extension
- **mascotas**: bano, corte, higiene

Semilla de servicios:

- **barberia**: corte clásico, corte fade, perfilado de barba, corte + barba, cejas, mascarilla negra, tinte de barba
- **estetica**: corte de dama, cepillado, secado, tinte, mechas, keratina, peinado de evento, depilación de cejas
- **unas**: manicura, pedicura, esmaltado semipermanente, uñas acrílicas, retiro
- **mascotas**: baño, baño + corte, corte de uñas, limpieza de oídos, deslanado

Las verticales son un campo del catálogo, **no tipos distintos de local**. Así un local puede ofrecer corte de caballero y uñas sin dos modelos paralelos, y el filtro del usuario sale gratis.

```
servicio_local
  id                    uuid PK
  local_id              uuid FK -> local
  catalogo_servicio_id  uuid FK -> catalogo_servicio
  precio                numeric(10,2) CHECK (>= 0)
  precio_desde          boolean       -- true => "desde $X"
  duracion_min          smallint CHECK (> 0)
  buffer_min            smallint DEFAULT 0   -- limpieza posterior
  comisionable          boolean DEFAULT true
  activo                boolean
  UNIQUE (local_id, catalogo_servicio_id)

servicio_local_tamano
  servicio_local_id  uuid FK
  tamano_id          uuid FK -> tamano_mascota
  precio             numeric(10,2)
  duracion_min       smallint
  PK (servicio_local_id, tamano_id)
```

`servicio_local_tamano` es obligatorio para grooming: el precio y la duración dependen del animal. Bañar un yorkshire no cuesta lo mismo que un golden.

```
solicitud_catalogo
  id                    uuid PK
  local_id              uuid FK
  vertical_id           uuid FK -> vertical
  nombre_propuesto      varchar
  descripcion           text NULL
  estado                enum(pendiente, aprobada, rechazada)
  catalogo_servicio_id  uuid FK NULL   -- se llena al aprobar
  motivo_rechazo        text NULL

producto
  id            uuid PK
  local_id      uuid FK -> local
  nombre        varchar         -- pomada, cera, shampoo
  precio        numeric(10,2)
  comision_pct  numeric(5,2) DEFAULT 0
  activo        boolean
```

Los productos son necesarios para que la liquidación de comisiones sea correcta: llevan porcentaje distinto (o cero) al de un servicio.

### 4.6 Módulo Staffing

```
profesional
  id               uuid PK
  usuario_id       uuid FK -> usuario NULL UNIQUE   -- puede existir sin cuenta
  nombre           varchar
  alias            varchar NULL      -- "Kevin el Fade"
  bio              text NULL
  foto_url         varchar NULL
  independiente    boolean           -- renta silla vs empleado
  perfil_publico   boolean DEFAULT true
  traslado_min     smallint DEFAULT 30
```

`usuario_id` nullable a propósito: muchos barberos no van a instalar nada y el local gestiona su agenda. El perfil existe igual.

`traslado_min`: minutos mínimos de separación cuando dos citas consecutivas son en locales distintos. **No lo puede validar un constraint** — la base no sabe de geografía. Va en el motor de disponibilidad.

```
profesional_foto
  id               uuid PK
  profesional_id   uuid FK
  url              varchar
  orden            smallint
```

El portafolio del profesional importa más de lo que parece: la gente escoge barbero viendo cortes, no leyendo precios. Es probablemente el mejor mecanismo de descubrimiento del producto.

```
asignacion
  id               uuid PK
  local_id         uuid FK -> local
  profesional_id   uuid FK -> profesional
  rol              enum(barbero, estilista, manicurista, groomer, recepcion)
  modalidad        enum(empleado, renta_silla, invitado)
  comision_pct     numeric(5,2) CHECK (0..100)
  desde            date
  hasta            date NULL        -- NULL = vigente
```

El vínculo laboral, **sin horarios**. Vigencia: `hasta IS NULL OR hasta >= CURRENT_DATE`. Un profesional puede tener varias asignaciones vigentes simultáneas (día en un local, noche en otro).

```
turno
  id               uuid PK
  asignacion_id    uuid FK -> asignacion
  profesional_id   uuid FK -> profesional    -- desnormalizado
  local_id         uuid FK -> local          -- desnormalizado
  dia_semana       smallint CHECK (0..6)
  entra            time
  sale             time
  vigente_desde    date
  vigente_hasta    date NULL
  CHECK (sale > entra)

  -- columnas generadas, para el constraint de exclusión
  rango      franja    GENERATED AS franja(entra, sale, '[)')
  vigencia   daterange GENERATED AS daterange(vigente_desde, vigente_hasta, '[)')
```

Ejemplo — Kevin trabaja en dos locales:

```
asignacion #1  Kevin → Alborada    lun..vie  09:00–14:00
asignacion #2  Kevin → Urdesa      lun..vie  18:00–23:00
                                   sáb       10:00–20:00
```

Los campos desnormalizados (`profesional_id`, `local_id`) existen **solo** para habilitar el constraint de §4.7. Se escriben en la misma transacción que la asignación.

**Turnos que cruzan medianoche:** `sale > entra` falla si el barbero sale a la 1:00am. Se parte en dos filas (mié 20:00–23:59 + jue 00:00–01:00). La alternativa —minutos desde el inicio de la semana en `int4range`— es más elegante pero menos legible en el admin; para barbería, partir alcanza.

```
turno_fecha
  id               uuid PK
  profesional_id   uuid FK
  local_id         uuid FK
  fecha            date
  entra            time NULL
  sale             time NULL
  tipo             enum(extra, reemplaza, cancela)
  nota             text NULL
  CHECK (tipo = 'cancela' AND entra IS NULL AND sale IS NULL
         OR tipo <> 'cancela' AND entra IS NOT NULL AND sale IS NOT NULL AND sale > entra)
```

Overrides por fecha concreta ("este sábado no voy a Urdesa, voy a Alborada"). No se modifica el turno recurrente. El cálculo de disponibilidad aplica primero los recurrentes vigentes y luego los overrides de esa fecha.

```
recurso
  id                uuid PK
  local_id          uuid FK -> local
  tipo_recurso_id   uuid FK -> tipo_recurso (§4.5) -- nunca "ninguno": un recurso físico siempre es algo concreto
  nombre            varchar(60)     -- "Silla 3", "Mesa 1"
  activo            boolean
```

**Una fila por unidad física, nunca una fila con `cantidad = 3`.** Con un campo cantidad, el constraint de exclusión de citas subvende (solo permite una a la vez) o, si se relaja, sobrevende. Además una fila por unidad permite marcar una mesa fuera de servicio sin afectar las otras.

Un local puede tener 4 barberos pero 1 sola mesa de uñas: dos manicuristas libres y aun así no se pueden agendar dos manicuras a la misma hora.

```
habilidad
  id                  uuid PK
  profesional_id      uuid FK -> profesional
  servicio_local_id   uuid FK -> servicio_local
  precio_override     numeric(10,2) NULL   -- senior cobra más
  UNIQUE (profesional_id, servicio_local_id)
```

**La tabla que todos olvidan y la que rompe el agendamiento.** No todos los barberos hacen todo. Si el cliente agenda uñas y el sistema le asigna al barbero que solo hace fades, hay problema el día uno. Regla: una cita solo puede asignarse a un profesional con habilidad para ese servicio.

```
excepcion
  id               uuid PK
  local_id         uuid FK NULL      -- cierre de todo el local
  profesional_id   uuid FK NULL      -- ausencia individual
  recurso_id       uuid FK NULL      -- recurso fuera de servicio
  fecha_inicio     timestamptz
  fecha_fin        timestamptz
  motivo           enum(feriado, vacaciones, mantenimiento, personal, bloqueo_manual)
  nota             text NULL
  CHECK (fecha_fin > fecha_inicio)
  CHECK (local_id IS NOT NULL OR profesional_id IS NOT NULL OR recurso_id IS NOT NULL)
```

Una ausencia del profesional (`profesional_id` con `local_id` NULL) lo bloquea en **todos** sus locales: no está enfermo solo en una sucursal.

### 4.7 Módulo Scheduling

```
cliente_local
  usuario_id                uuid FK
  local_id                  uuid FK
  primera_cita_at           timestamptz NULL
  ultima_cita_at            timestamptz NULL
  total_citas               int DEFAULT 0
  nota                      text NULL
  profesional_preferido_id  uuid FK NULL
  PK (usuario_id, local_id)
```

`cita.cliente_nuevo` se calcula contra esta tabla: si no existe la fila, es cliente nuevo para ese local. `nota` es lo que hace que el cliente vuelva y lo que un barbero suplente necesita cuando el titular no está: "fade 2 a los lados, tijera arriba", "tinte 7.3 + 20 vol".

```
cita
  id                uuid PK
  local_id          uuid FK -> local
  profesional_id    uuid FK -> profesional
  recurso_id        uuid FK -> recurso NULL
  cliente_id        uuid FK -> usuario

  inicio            timestamptz
  fin               timestamptz
  rango             tstzrange GENERATED AS tstzrange(inicio, fin, '[)')

  estado            varchar(32)   -- ver §6
  canal             varchar(16)   -- app, local, whatsapp

  precio_total      numeric(10,2)
  propina           numeric(10,2) DEFAULT 0 CHECK (>= 0)
  metodo_pago       varchar(16) NULL   -- efectivo, transferencia, tarjeta, payphone

  cliente_nuevo     boolean

  para_tipo         varchar(16)   -- titular, otra_persona, mascota
  para_nombre       varchar NULL
  mascota_id        uuid FK NULL
  CHECK (para_tipo <> 'mascota' OR mascota_id IS NOT NULL)

  nota_cliente      text NULL
  codigo            varchar(8) UNIQUE   -- código corto para el local

  reagendada_de_id  uuid FK -> cita NULL
  expira_at         timestamptz NULL    -- hold

  created_at, updated_at
  confirmada_at, cancelada_at, completada_at
  CHECK (fin > inicio)
```

**Sobre `canal`:** los walk-ins tienen que entrar a la agenda (`canal = 'local'`). Si el local atiende sin cita y no lo registra, la disponibilidad miente y el cliente de la app llega a esperar. Eso mata la confianza más rápido que cualquier bug. El módulo de agenda del local tiene que ser lo bastante bueno para que lo usen para **todo**.

**Sobre `propina`:** va 100% al profesional y **no es base de comisión**. Si se suma al total de la cita, se le cobra comisión al dueño sobre la propina del barbero. Pelea garantizada.

**Sobre `reagendada_de_id`:** reagendar no es cancelar + crear. Cancelar+crear le cuenta una cancelación al cliente que sí avisó y pierde la trazabilidad.

```
cita_item
  id                  uuid PK
  cita_id             uuid FK -> cita
  servicio_local_id   uuid FK -> servicio_local
  precio              numeric(10,2)   -- CONGELADO al agendar
  duracion_min        smallint
  comisionable        boolean
  comision_pct        numeric(5,2)    -- CONGELADO al agendar

cita_producto
  id            uuid PK
  cita_id       uuid FK -> cita
  producto_id   uuid FK -> producto
  cantidad      smallint
  precio        numeric(10,2)   -- congelado
  comision_pct  numeric(5,2)    -- congelado
```

**Precio congelado:** si el local sube precios mañana, la cita agendada ayer mantiene el precio pactado. Es requisito, no detalle.

**Comisión congelada:** si el dueño le cambia el porcentaje al barbero, las citas ya atendidas se liquidan con el porcentaje vigente cuando se atendieron. Sin esto, cambiar una comisión reescribe el pasado y se genera un reclamo.

```
cita_evento
  id                  uuid PK
  cita_id             uuid FK -> cita
  estado_anterior     varchar(32) NULL
  estado_nuevo        varchar(32)
  actor_usuario_id    uuid NULL
  actor_rol           varchar(32) NULL
  payload             jsonb NULL
  created_at          timestamptz
```

Auditoría inmutable. Cuando el barbero diga "esa cita fue mía y no me la pagaron", esto es la única respuesta posible. Para comisiones no es opcional: es plata entre dos personas.

```
espera
  id                  uuid PK
  local_id            uuid FK
  cliente_id          uuid FK
  profesional_id      uuid FK NULL
  servicio_local_id   uuid FK
  fecha_deseada       date
  desde               time NULL
  hasta               time NULL
  estado              enum(activa, notificada, convertida, expirada)
```

Lista de espera: cuesta poco, retiene mucho y convierte cancelaciones en citas. En barbería los sábados se llenan; se usaría todo el tiempo.

```
disponibilidad_dia
  local_id         uuid FK
  fecha            date
  slots_libres     smallint
  primer_slot      time NULL
  ultimo_slot      time NULL
  recalculado_at   timestamptz
  PK (local_id, fecha)
```

**Read model.** El filtro "disponible hoy" no puede correr el motor de slots por cada local del resultado: se cae. La búsqueda consulta esto, que un job reconstruye por evento.

```
idempotencia
  id            uuid PK
  clave         varchar(64) UNIQUE
  usuario_id    uuid NULL
  endpoint      varchar(120)
  status        smallint
  respuesta     jsonb
  created_at    timestamptz
```

`id` es uuid como toda tabla del esquema (regla del proyecto, sin excepción) aunque `clave` sea el identificador natural que manda el cliente — `clave` queda como columna UNIQUE normal en vez de ser la propia PK.

En móvil la red se cae a mitad del POST, el usuario vuelve a tocar "Agendar" y se crean dos citas. **Pasa siempre.** Todo endpoint de escritura recibe un header `Idempotency-Key`; si la clave repite, se devuelve la respuesta guardada sin ejecutar nada.

### 4.8 Módulo Reviews

```
resena
  id                    uuid PK
  cita_id               uuid FK -> cita UNIQUE
  local_id              uuid FK      -- desnormalizado para consultas
  profesional_id        uuid FK
  puntaje_local         smallint CHECK (1..5)
  puntaje_profesional   smallint CHECK (1..5) NULL
  puntualidad           smallint CHECK (1..5) NULL
  limpieza              smallint CHECK (1..5) NULL
  comentario            text NULL
  estado                enum(publicada, en_revision, oculta)
  respuesta_local       text NULL
  respuesta_at          timestamptz NULL

reporte
  id             uuid PK
  tipo            enum(resena, foto, local, profesional)
  objeto_id       uuid
  reportante_id   uuid FK -> usuario
  motivo          enum(difamacion, contenido_inapropiado, falso, spam, otro)
  detalle         text NULL
  estado          enum(pendiente, resuelto, descartado)
```

Reglas duras de reseña:

- Solo se puede reseñar una cita en estado `completada`
- Ventana de 14 días desde la cita; después se cierra
- `UNIQUE (cita_id)` — una reseña por cita. Sin esto, el barbero se autoreseña y el ranking no vale nada

### 4.9 Módulo Notifications

```
device_token
  id             uuid PK
  usuario_id     uuid FK
  token          varchar
  plataforma     enum(android, ios)
  apns_token     varchar NULL       -- iOS: token APNs que FCM necesita
  app_version    varchar
  activo         boolean
  ultimo_uso_at  timestamptz

notificacion_categoria
  id       uuid PK
  codigo   varchar(20) UNIQUE   -- citas, agenda, social, promos
  nombre   varchar
  activo   boolean

preferencia_notificacion
  usuario_id     uuid FK
  categoria_id   uuid FK -> notificacion_categoria
  push           boolean
  whatsapp       boolean
  PK (usuario_id, categoria_id)

notificacion
  id                uuid PK
  usuario_id        uuid FK
  tipo_evento       varchar
  categoria_id      uuid FK -> notificacion_categoria
  canal             enum(push, whatsapp, sms, websocket)
  cita_id           uuid FK NULL
  plantilla         varchar NULL
  estado            enum(programada, enviada, entregada, leida, fallida, cancelada)
  programada_para   timestamptz
  enviada_at        timestamptz NULL
  proveedor_id      varchar NULL     -- id del mensaje en FCM o Meta
  collapse_id       varchar NULL
  costo_usd         numeric(8,5) DEFAULT 0
  error             text NULL
  UNIQUE (cita_id, tipo_evento, canal)

plantilla_whatsapp
  id          uuid PK
  nombre      varchar UNIQUE   -- lo asigna Meta al aprobar la plantilla
  categoria   enum(utility, authentication, marketing)
  idioma      varchar
  estado      enum(pendiente, aprobada, rechazada, pausada)
  variables   jsonb
```

`notificacion_categoria` es tabla de parámetros: antes era el mismo `varchar`+`CHECK` repetido en `preferencia_notificacion` y `notificacion`, las dos tablas del módulo que lo usan.

`plantilla_whatsapp.id` es uuid como toda tabla del esquema (regla del proyecto, sin excepción) aunque `nombre` sea el identificador que asigna Meta — `nombre` queda como columna UNIQUE normal en vez de ser la propia PK.

`UNIQUE (cita_id, tipo_evento, canal)` es el seguro contra duplicados por reintentos de la cola. `costo_usd` por fila permite saber el costo real por local.

### 4.10 Módulo Billing

```
suscripcion
  id                 uuid PK
  negocio_id         uuid FK -> negocio
  plan_id            uuid FK -> plan (§4.4)
  profesionales      smallint        -- base del precio
  precio_mensual     numeric(10,2)
  ciclo              enum(mensual, anual)
  estado             enum(activa, gracia, vencida, cancelada)
  vigente_hasta      date

cobro
  id                 uuid PK
  suscripcion_id     uuid FK
  monto              numeric(10,2)
  estado             enum(pendiente, pagado, fallido, reembolsado)
  intentos           smallint
  pagado_at          timestamptz NULL
  comprobante_sri    varchar NULL    -- clave de acceso factura electrónica

liquidacion
  id                 uuid PK
  local_id           uuid FK
  profesional_id     uuid FK
  periodo_desde      date
  periodo_hasta      date
  total_servicios    numeric(10,2)
  total_productos    numeric(10,2)
  total_propinas     numeric(10,2)
  comision_servicios numeric(10,2)
  comision_productos numeric(10,2)
  total_a_pagar      numeric(10,2)
  estado             enum(borrador, cerrada, pagada)
  cerrada_at         timestamptz NULL
```

`total_propinas` se suma al pago del profesional pero **no** entra en la base de comisión.

### 4.11 Constraints críticos

Los tres que hacen correcto el sistema. Sin ellos, la lógica de aplicación falla bajo concurrencia.

**1. Turnos de un profesional no se traslapan, aunque sean de locales distintos**

```sql
ALTER TABLE turno
  ADD CONSTRAINT turno_sin_traslape
  EXCLUDE USING gist (
    profesional_id WITH =,
    dia_semana     WITH =,
    rango          WITH &&,
    vigencia       WITH &&
  );
```

Sin esto, un local puede crear un turno que pisa el de otro local y el conflicto se descubre recién al agendar, con el cliente esperando.

`vigencia` es indispensable: sin ella, cuando el barbero cambia de horario a futuro el turno nuevo choca con el actual y Postgres lo rechaza aunque sea legítimo.

**2. Un profesional no puede tener dos citas traslapadas — en ningún local**

```sql
ALTER TABLE cita
  ADD CONSTRAINT cita_profesional_sin_traslape
  EXCLUDE USING gist (profesional_id WITH =, rango WITH &&)
  WHERE (estado IN ('reservada','confirmada','en_curso'));
```

**Nota deliberada:** es sobre `profesional_id` solo, **no** sobre `(local_id, profesional_id)`. Un profesional es un solo cuerpo y no puede estar en dos locales a la vez. Scopear el constraint por local —que es el instinto natural— introduce exactamente ese bug.

**3. Un recurso no puede estar ocupado dos veces**

```sql
ALTER TABLE cita
  ADD CONSTRAINT cita_recurso_sin_traslape
  EXCLUDE USING gist (recurso_id WITH =, rango WITH &&)
  WHERE (estado IN ('reservada','confirmada','en_curso') AND recurso_id IS NOT NULL);
```

El recurso pertenece a un local, así que este constraint ya es implícitamente por local.

### 4.12 Índices

```sql
CREATE INDEX local_ubicacion_gist      ON local USING gist (ubicacion);
CREATE INDEX local_estado_score        ON local (estado, score_ranking);
CREATE INDEX cita_local_inicio         ON cita (local_id, inicio);
CREATE INDEX cita_profesional_inicio   ON cita (profesional_id, inicio);
CREATE INDEX cita_cliente_inicio       ON cita (cliente_id, inicio DESC);
CREATE INDEX cita_holds_vencidos       ON cita (expira_at) WHERE estado = 'reservada';
CREATE INDEX turno_local_dia           ON turno (local_id, dia_semana);
CREATE INDEX excepcion_prof_rango      ON excepcion (profesional_id, fecha_inicio, fecha_fin);
CREATE INDEX habilidad_servicio        ON habilidad (servicio_local_id);
CREATE INDEX servicio_local_activo     ON servicio_local (local_id, activo);
```

### 4.13 Sobre particionado

`cita` queda como **tabla normal**, a propósito. Postgres exige que la clave primaria de una tabla particionada incluya la columna de partición —`PRIMARY KEY (id, inicio)`— lo que arrastra claves compuestas a `cita_item`, `cita_producto` y `resena`, y Eloquent maneja mal las claves compuestas.

Cuando haga falta (>5M filas), se particiona por la ruta clásica: crear `cita_part` particionada por mes, copiar por lotes, renombrar. Con el volumen proyectado (8.000 citas/día con 200 locales) eso está a años de distancia.

---

## 5. Motor de disponibilidad y slots

Aquí está el 90% de la dificultad del sistema y lo único que no se puede improvisar después.

### 5.1 Filtros que debe pasar un slot válido

1. El local está `activo` y abierto ese día/hora (`horario_local`)
2. El profesional tiene asignación vigente en ese local y **turno vigente** ese día (`turno`, con los overrides de `turno_fecha` aplicados)
3. El profesional tiene `habilidad` para **todos** los servicios pedidos
4. No hay `excepcion` que cubra esa ventana (local, profesional o recurso)
5. El profesional no tiene otra cita traslapada **en ningún local**
6. Si la cita adyacente del profesional es en otro local, hay al menos `traslado_min` de separación
7. Hay un recurso del tipo requerido libre en toda la ventana
8. `inicio >= now() + lead_time_min`
9. `inicio <= now() + horizonte_dias`
10. La ventana completa (duración + buffer) cabe antes del cierre del local

### 5.2 Granularidad

Candidatos cada **15 minutos**. No cada minuto (ruido, y el barbero no piensa así) ni cada hora (se pierden slots reales).

### 5.3 Concurrencia

Dos clientes pidiendo el mismo slot al mismo tiempo es el escenario normal, no el borde. "Consultar disponibilidad y luego insertar" **falla siempre** bajo concurrencia.

**No usar locks para agendar.** El instinto es `SELECT ... FOR UPDATE` sobre el slot, pero eso serializa todas las reservas del local y agrega latencia en el momento de mayor carga. Es innecesario: la tasa real de colisión sobre el mismo slot es baja.

Camino optimista: intentar el INSERT y capturar la violación del constraint.

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

**Donde sí hace falta lock:** la liquidación de comisiones. Bloquear el rango del periodo mientras se liquida, o se liquida una cita que se modificó a mitad del cálculo. Ahí sí es plata y sí importa la consistencia total.

### 5.4 Reserva temporal (hold)

Cuando el cliente escoge slot pero aún no confirma: cita en estado `reservada` con `expira_at = now() + 10 min`. Un job la libera si no confirma.

**No confiar en que el job corrió.** Filtrar también en la consulta (`estado = 'reservada' AND expira_at > now()`). Si el worker se cayó 20 minutos, sin ese filtro hay slots fantasma bloqueados.

### 5.5 Regla de oro del caché

**Se cachea para mostrar, nunca para decidir.** La grilla se pinta desde Redis; el agendamiento se valida contra Postgres con el constraint. Si se confía en el caché al escribir, se sobrevende.

### 5.6 Casos borde a decidir

| Caso | Qué pasa |
|---|---|
| El servicio se alarga y pisa la siguiente cita | El local marca `en_curso` y el sistema avisa al siguiente cliente del retraso |
| El barbero no llegó | El local bloquea su día (`excepcion`); el sistema notifica y ofrece reagendar |
| Cliente cancela 10 min antes | Se registra `cancelada_tarde`, cuenta para su historial de confiabilidad |
| Cliente no llegó | `no_show` — métrica clave para el local y para el score del cliente |
| Cita creada en el local (walk-in) | `canal = local`, ocupa slot igual |
| Cliente con 3 no-shows | `requiere_confirmacion = true`; debe confirmar para que el slot se le reserve |

### 5.7 Tests obligatorios

Es el único componente donde un bug cuesta clientes y reputación. Suite propia con: solapamientos, turnos multi-local, traslado entre locales, recursos ocupados, excepciones, cruces de medianoche, holds vencidos, y **un test de concurrencia real** que lance 20 requests paralelos al mismo slot y verifique que exactamente uno gana.

---

## 6. Máquina de estados de la cita

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

Estados terminales: `completada`, `cancelada_cliente`, `cancelada_local`, `no_show`, `expirada`, `reagendada`.

- Solo `completada` habilita reseña
- Solo `completada` cuenta para el ranking del local y para la liquidación de comisiones
- `reagendada` no penaliza al cliente
- Cada transición escribe una fila en `cita_evento`
- Cada transición **cancela y reprograma** las notificaciones pendientes de esa cita

---

## 7. Reputación y ranking

### 7.1 Promedio bayesiano

Un local con una sola reseña de 5.0 no puede aparecer sobre uno con 80 reseñas de 4.7.

```
score = (v / (v + m)) * R + (m / (v + m)) * C

v = número de reseñas del local
R = promedio del local
m = 10   (peso del prior, ajustable)
C = promedio global de la plataforma
```

### 7.2 Mientras haya pocos datos

No mostrar "5.0 ★" con 3 reseñas — es información falsa y el usuario la castiga. En su lugar:

- "Atendió 42 citas este mes"
- Etiquetas agregadas: puntual, buen fade, bueno con niños, local limpio
- "Nuevo en la plataforma" como badge honesto

Encender las estrellas cuando el local pase ~10 reseñas.

### 7.3 Factores del ranking orgánico

Ninguno se puede comprar:

- Distancia al usuario (peso alto)
- Score bayesiano
- Citas completadas en los últimos 30 días
- Tasa de no-show y de cancelación **por parte del local** (penaliza)
- Tiempo de respuesta a solicitudes
- Completitud del perfil (fotos, servicios con precio, horarios)
- Verificado (bonus)

El bloque "Destacados" va arriba, separado y etiquetado. Máximo 2 resultados. Si se mezcla pago con orgánico, se pierde lo único que diferencia el producto de Instagram.

El score se recalcula por **job nocturno**, nunca en el request.

---

## 8. Búsqueda

```sql
SELECT l.*, ST_Distance(l.ubicacion, :punto) AS distancia_m
FROM local l
JOIN servicio_local sl ON sl.local_id = l.id
JOIN catalogo_servicio cs ON cs.id = sl.catalogo_servicio_id
JOIN servicio_categoria sc ON sc.id = cs.categoria_id
LEFT JOIN disponibilidad_dia dd ON dd.local_id = l.id AND dd.fecha = :fecha
WHERE l.estado = 'activo'
  AND ST_DWithin(l.ubicacion, :punto, :radio_m)
  AND sc.vertical_id = :vertical_id
  AND sl.precio BETWEEN :min AND :max
  AND (:solo_disponibles = false OR dd.slots_libres > 0)
ORDER BY l.score_ranking DESC, distancia_m ASC
LIMIT 20;
```

Filtros de la v1: vertical, servicio específico, rango de precio, distancia, disponibilidad hoy/mañana, amenidades, abierto ahora.

El filtro de disponibilidad **consulta `disponibilidad_dia`**, no el motor de slots. Correr el motor por cada local del resultado, en cada scroll, es insostenible.

---

## 9. Monetización

### 9.1 El principio

Dos formas de hacer que un barbero pague:

1. **Romper algo a propósito** y vender el arreglo (limitar citas es esto). Funciona un mes, genera resentimiento, y resta el tráfico que el marketplace necesita.
2. **Hacerle trabajo real** que no puede replicar en WhatsApp ni en Excel. Paga porque le duele más no tenerlo.

Solo la segunda sostiene un negocio. Regla operativa: **se limitan funciones, nunca volumen.**

### 9.2 Las tres funciones que se pagan

**Liquidación de comisiones — el killer feature.** Casi toda barbería trabaja a porcentaje (50/50, 60/40). Cada quincena el dueño cuadra a mano cuánto produjo cada barbero. Es una hora perdida, se equivoca, y cuando se equivoca hay conflicto.

Como cada cita ya tiene profesional asignado y precio y comisión congelados, la liquidación sale directa del modelo de datos. Toca plata, es recurrente, causa conflicto humano y es imposible en WhatsApp. Un dueño que liquida por la app no se va, porque irse es volver al cuaderno.

**Agenda multi-barbero.** Cuatro sillas = cuatro agendas independientes + consolidado del día. Estructuralmente inexistente en WhatsApp. Es la frontera natural del plan gratis y es honesta: no se rompe nada, es una función que sirve solo a locales con varios barberos — justo los que tienen presupuesto.

**Recordatorios automáticos (reducción de no-show).** El que se vende con números. Mecánica clave del freemium:

> **En el plan gratis se muestra la pérdida, no la solución.**
>
> "Este mes 9 clientes no llegaron. A tu precio promedio de $9 son $81 no facturados. Los locales con recordatorios automáticos reducen esto ~55%."

El dato es suyo; la plataforma solo lo cuenta. Convierte mejor que un muro y no genera resentimiento.

### 9.3 Nunca cobrar por esto

- Número de citas
- Aparecer en la búsqueda
- Recibir reseñas
- Badge de verificado
- Agenda de un solo barbero

Cada cita cumplida en un local gratis es densidad y reputación para la plataforma. Cobrar por eso es cobrarse a sí mismo.

### 9.4 Tabla de planes

| | Free | Pro |
|---|---|---|
| Citas | Ilimitadas | Ilimitadas |
| Aparecer en búsqueda | Sí | Sí |
| Recibir reseñas | Sí | Sí |
| Verificado | Se gana igual | Se gana igual |
| Locales | 1 | Varios |
| Profesionales | 1 | Ilimitados, agenda individual |
| Liquidación de comisiones | Solo total, sin desglose | Completa y exportable |
| Recordatorios WhatsApp | No | Sí |
| Responder reseñas | No | Sí |
| Estadísticas | Solo pérdida por no-show | Completas |
| Promociones y horas valle | No | Sí |
| Fotos | 3 | Galería completa |
| Bloque Destacados | No | Incluido |

### 9.5 Momentos de conversión

El muro va donde el dueño ya siente el dolor, no en un correo:

| Disparador | Qué ve |
|---|---|
| Intenta registrar un 2º profesional | Muro directo — es estructural y justo |
| Cierre de mes | Liquidación **calculada y visible pero bloqueada**: ve el total, no el desglose |
| 3er no-show del mes | La pérdida en dólares + qué la evita |
| Recibe reseña ≤3★ | "Responder reseñas es Pro" |
| A los 30 días de uso | "Tu hora más pedida es sábado 2pm. Los martes tienes 11 slots vacíos." |

### 9.6 Precio

**$8 el primer profesional + $5 por cada adicional, mensual.** Local de 4 sillas = $23.

Encuadre de venta: *cuesta menos de 3 cortes al mes*. Con un corte a $5–8 en Guayaquil, un local de 4 barberos paga el equivalente a 3 cortes por tener 4 agendas, recordatorios automáticos y las comisiones cuadradas.

Plan anual con 2 meses gratis: caja por adelantado y menos churn. Se cobra **al negocio, no al local**.

### 9.7 Secuencia

**Todo gratis los primeros ~6 meses**, Pro incluido. No hay nada que vender hasta poder entrar a un local y decir "en septiembre te mandamos 34 clientes nuevos".

Al encender el Pro, arrancar solo con lo que funciona desde el día uno: **comisiones, multi-barbero y recordatorios**. Las estadísticas necesitan 2–3 meses de datos para convencer a alguien; un dashboard con 14 citas no vende.

---

## 10. Pagos y transacciones

### 10.1 v1: nada de dinero por la plataforma

Si la plata del cliente pasa por la plataforma y después se le paga al barbero, se deja de ser una app de citas y se pasa a ser intermediario financiero. Implicaciones:

- **Peso legal y tributario**: se recibe dinero de un servicio que no se presta y que luego se liquida a un tercero. Pago dividido, facturación de lo propio vs lo administrado, retenciones. Confirmar con un contador.
- **Costo operativo invisible**: cada reembolso, contracargo y "no me llegó la plata" es un ticket resuelto a mano, probablemente el sábado en la noche.
- **Realidad del efectivo**: el sector es mayormente efectivo. El barbero no quiere pagar 4–5% de pasarela por algo que hoy cobra en billetes, y parte del cliente no tiene tarjeta.

Y lo decisivo: **la liquidación de comisiones no necesita que la plataforma maneje plata.** Solo necesita saber qué se cobró y a quién se asignó. El killer feature funciona con cero dependencia de pagos.

En la v1 solo se registra `cita.metodo_pago` (efectivo, transferencia, tarjeta, Payphone) para que la comisión salga bien.

### 10.2 v1.5: cobro sin enrutar dinero

Generar dentro de la app un **link o QR de cobro** (Payphone, De Una, transferencia) con el monto de la cita ya cargado. El cliente paga, la plata va directo barbero ↔ cliente, la plataforma nunca la toca; solo **registra** que se pagó y con qué método.

80% de la comodidad con 5% del riesgo: el barbero no tipea montos, el cobro queda vinculado a la cita, la liquidación sale exacta. Y es honesto: muchos barberos ya cobran con Payphone o De Una.

### 10.3 v2: abono, si los datos lo justifican

El abono no es un pago, es un **compromiso**. $2–3 al reservar, descontables del servicio. Su propósito es que el cliente no falte.

Tres variantes y su costo:

| Variante | A favor | En contra |
|---|---|---|
| **Abono de la plataforma** (tarifa de reserva) | No se administra plata de terceros; se factura directo | No es descontable del servicio (el barbero no honra un descuento que no recibió). El cliente lo siente como "me cobraron por agendar" |
| **Abono del barbero, liquidado** | Producto mucho mejor: el cliente ve el descuento, el barbero se protege | Se vuelve a administrar fondos de terceros; ciclos de liquidación, reembolsos, pasarela con split |
| **Tarjeta registrada sin cobrar**, penalidad si no llega | Menos fricción, fuerte disuasión | Cobrar penalidades genera más disputas que cualquier otra cosa; en un mercado de baja confianza espanta desde el registro |

**Criterio de decisión: el dato decide.** Con `no_show` ya en el modelo, en seis meses se sabe si la tasa real es 8% o 25%. Con 8% el abono no vale la complejidad; con 25% sí, y se vende con el número en la mano.

### 10.4 Facturación electrónica

Al empezar a cobrar suscripciones hay que emitir comprobante electrónico: RUC, firma electrónica y proveedor de facturación. Pequeño en apariencia, pero **bloquea el cobro si no está listo**. Verificar requisitos vigentes en el SRI.

---

## 11. Notificaciones

### 11.1 Canales y su papel

| Canal | Costo | Llega cuando | Para qué |
|---|---|---|---|
| FCM push | Gratis | App instalada, permiso dado, token vivo | Todo lo que se pueda |
| WhatsApp Cloud API | Por mensaje entregado | Siempre | OTP, confirmación, recordatorio de 2h |
| WebSocket (Reverb) | Gratis | App abierta | Panel del local en vivo |
| SMS | Caro en Ecuador | Siempre | Solo fallback de OTP |

Email no se considera: en este mercado no se abre.

Regla: **push por defecto; WhatsApp solo donde la entrega importa de verdad.** El push falla más de lo que parece — permiso denegado, app desinstalada, token caducado, optimizadores de batería.

### 11.2 Matriz

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

**Al profesional nunca se le manda WhatsApp.** Tiene la app abierta todo el día y el costo es de la plataforma.

**Dos recordatorios por cita, no más.** El de 24 h permite reagendar; el de 2–3 h es el que reduce el no-show.

### 11.3 Costo de WhatsApp

Desde el 1 de julio de 2025 Meta cobra por cada mensaje de plantilla entregado, por categoría (marketing, utility, authentication) y país del destinatario — ya no por ventana de conversación. Los recordatorios son **utility**, la categoría barata: utility y authentication suelen estar bajo $0.03 por mensaje. Los rate cards se actualizan trimestralmente; verificar la tarifa vigente para Ecuador antes de fijar el precio de la suscripción.

Cuenta base: 300 citas/mes × 1 recordatorio utility ≈ $6/mes contra $23 de suscripción. Sano. Con 4 mensajes por cita ≈ $24 y el cliente pasa a pérdida. De ahí que casi todo vaya por push.

Dos cambios a tener presentes:

- **La ventana gratuita se termina.** Hoy los mensajes dentro de una conversación iniciada por el cliente son gratis, incluidas plantillas utility, pero desde el 1 de octubre de 2026 los mensajes de servicio y las respuestas utility dentro de la ventana vuelven a ser facturables. No diseñar la economía asumiendo mensajes gratis.
- **Las promociones tienen tope.** Son categoría marketing y Meta limita cuántas recibe un usuario en 24 h sumando todas las empresas (~2 al día), devolviendo el error 131049. Las promociones de hora valle del Pro van por **push**.

Control: presupuesto de mensajes por local y por mes. Al superarlo, degradar a solo push y avisar al dueño.

### 11.4 Payload FCM

Híbrido: `notification` para que el sistema la muestre siempre, más `data` con los ids para el deep link.

```php
[
  'token' => $deviceToken,
  'notification' => [
     'title' => 'Tu cita fue confirmada',
     'body'  => 'Mañana 10:00 en Barbería X',
  ],
  'data' => [
     'tipo'    => 'cita_confirmada',
     'cita_id' => $cita->id,
     'ruta'    => '/citas/'.$cita->id,
  ],
  'android' => [
     'priority' => 'high',
     'notification' => [
        'channel_id' => 'citas',
        'tag'        => 'cita_'.$cita->id,
     ],
  ],
  'apns' => [
     'headers' => [
        'apns-priority'    => '10',
        'apns-push-type'   => 'alert',
        'apns-collapse-id' => 'cita_'.$cita->id,
     ],
     'payload' => ['aps' => [
        'sound'              => 'default',
        'badge'              => $noLeidas,
        'interruption-level' => 'time-sensitive',
        'mutable-content'    => 1,
     ]],
  ],
]
```

Sin el bloque `data` no hay deep link, y sin deep link la notificación es un aviso muerto.

### 11.5 iOS

**Setup previo (bloquea el desarrollo si falta):**

1. Cuenta de Apple Developer de pago. Sin eso no hay push.
2. Crear una **APNs Auth Key (.p8)** en el portal de Apple y subirla a Firebase con su Key ID y el Team ID. Se prefiere la key sobre los certificados porque no caduca.
3. En Xcode: capabilities **Push Notifications** y **Background Modes → Remote notifications**.
4. **No se puede probar push en el simulador** con FCM real. Dispositivo físico obligatorio.
5. `PrivacyInfo.xcprivacy` (privacy manifest) — Apple lo exige para SDKs de terceros, Firebase incluido. Sin esto App Store rechaza el build.

**Particularidades que no existen en Android:**

| Tema | Detalle |
|---|---|
| **Badge absoluto** | iOS no incrementa: se manda el número final. El backend debe calcular las no leídas en cada envío. Con `badge: 1` fijo, el contador queda clavado en 1 para siempre. |
| **`interruption-level: time-sensitive`** | Atraviesa modos de Concentración y el Resumen Programado. Indispensable para el recordatorio de 2 h: sin esto iOS puede retenerlo y entregarlo cuando la cita ya pasó. Requiere el entitlement `com.apple.developer.usernotifications.time-sensitive`, que se solicita en el portal. Usarlo solo en recordatorios y cancelaciones. |
| **`critical`** | Alertas críticas: entitlement especial que Apple concede casi solo a apps médicas o de seguridad. No aplica. |
| **`apns-collapse-id`** | Reemplaza una notificación anterior con el mismo id en vez de apilar. Ideal para cita reagendada. Máx 64 bytes. Equivalente Android: `tag`. |
| **`apns-push-type`** | Obligatorio desde iOS 13: `alert` o `background`. |
| **Push silencioso** | `content-available: 1` está fuertemente limitado (~2–3/hora), requiere `apns-priority: 5` y deja de llegar si el usuario mata la app. **Nunca depender de él**: la agenda se resincroniza por HTTP al abrir. |
| **Autorización provisional** | `requestPermission(provisional: true)` entrega al centro de notificaciones sin preguntar. Buena estrategia para no gastar el único intento en el primer arranque. |
| **Notification Service Extension** | Necesaria para notificaciones con imagen y para métricas de entrega de FCM en iOS. Requiere `mutable-content: 1`. Opcional en v1. |
| **Acciones** | Botones "Confirmar" / "Reagendar" en la notificación. Para el recordatorio de 2 h baja fricción y alimenta `requiere_confirmacion`. |

### 11.6 Android

**Canales (obligatorios desde Android 8):**

- `citas` — importancia alta, con sonido
- `agenda` — importancia alta (para el profesional)
- `social` — importancia por defecto (reseñas)
- `promos` — importancia baja, sin sonido

Si todo va en un canal, el usuario que se harta de las promos apaga todo y se pierde el recordatorio de cita — que es justo la función que se cobra en el Pro.

- **`POST_NOTIFICATIONS`** obligatorio desde Android 13, en runtime.
- **Optimizadores de batería de fabricantes.** Xiaomi, Huawei, Oppo y Realme —buena parte del mercado en Guayaquil— retrasan o descartan notificaciones. Mitigación: `priority: high`, y el recordatorio crítico de 2 h va por WhatsApp igual. Probar en dispositivo de esa marca.
- **Notification trampolines.** Desde Android 12 no se puede lanzar una Activity desde un Service o BroadcastReceiver al tocar la notificación. El `PendingIntent` apunta directo a la Activity.

### 11.7 Flutter

Paquetes: `firebase_messaging` + `flutter_local_notifications`.

| Estado de la app | Comportamiento | Qué se usa |
|---|---|---|
| Foreground | Android no la muestra sola | `onMessage` + local_notifications |
| Background | El sistema la muestra | `onMessageOpenedApp` al tocarla |
| Terminada | El sistema la muestra | `getInitialMessage()` en el arranque |

El tercero es el que más se olvida: si la app estaba cerrada, `onMessageOpenedApp` **no** se dispara. Sin consultar `getInitialMessage()`, el usuario toca la notificación, cae en el home y parece un bug.

**Ciclo de vida del token.** Escribir al backend en cuatro momentos: al iniciar sesión, al aceptar el permiso, en `onTokenRefresh` (FCM rota el token sin avisar) y al reinstalar. **Al cerrar sesión, borrar el token del backend** — si no, el dueño anterior del teléfono sigue recibiendo las citas de otra persona. Eso es un incidente de datos personales, no un bug menor.

**Probar siempre en release y en dispositivo físico.** El push se comporta distinto en debug y en emulador.

### 11.8 Estrategia de permisos

Hay un solo intento real: si el usuario rechaza, recuperarlo requiere que vaya a ajustes del sistema.

**No pedirlo en el primer arranque.** Pedirlo después de que agende su primera cita, con contexto:

> "¿Te avisamos de tus citas? Te recordamos 2 horas antes para que no se te pase."

La diferencia de aceptación es grande y la tasa de no-show depende directamente de esto.

### 11.9 Errores a prevenir por diseño

1. **Enviar el recordatorio tres veces.** El job falla a mitad, la cola reintenta. El constraint unique lo bloquea; además marcar `enviada` en la misma transacción y usar `proveedor_id` como idempotencia contra Meta.
2. **No cancelar notificaciones programadas.** El cliente reagenda del sábado al lunes y el recordatorio del sábado se dispara igual. Cada cambio de estado debe **cancelar y reprogramar** las pendientes. Es el bug más común en apps de citas y el que más confianza destruye.
3. **Enviar a las 6am.** Encolar en ventana 8:00–21:00 hora de Guayaquil. Si el recordatorio de 2 h cae de madrugada, enviarlo la noche anterior.
4. **Acumular tokens muertos.** FCM devuelve `UNREGISTERED` o `INVALID_ARGUMENT`: marcar `activo = false`. Si no, la tasa de entrega miente.
5. **Push y WhatsApp simultáneos.** Se paga siempre y el cliente recibe doble. Decidir por estado: si hay token activo y abrió la app en los últimos 7 días, solo push.
6. **Empezar el trámite de plantillas tarde.** La aprobación de Meta toma días y el texto no se puede cambiar sin volver a aprobar. Dejar aprobadas desde temprano: confirmación, recordatorio 24 h, recordatorio 2 h, cancelación, cupo liberado y OTP. **Este trámite bloquea el lanzamiento si se deja al final.**

### 11.10 Panel del local

Ahí el push es redundante porque el WebSocket ya pinta la cita en pantalla, pero **necesita sonido** — la recepción no está mirando fijo. Se resuelve con `flutter_local_notifications` disparado por el evento del WebSocket, más push solo en background.

Y al reconectar el WebSocket: **resincronizar por HTTP, no asumir continuidad.** Los eventos WebSocket actualizan la vista, nunca son la fuente de verdad. Si el panel se construye solo de eventos, tarde o temprano muestra una agenda incorrecta — y eso es peor que ninguna.

---

## 12. Arquitectura técnica

### 12.1 Dónde está la carga real

El problema no es "masivo", es **desbalanceado**:

- **Escrituras**: 200 locales × 40 citas/día = 8.000 citas diarias ≈ 0,2/s promedio, 3–5/s en el pico del sábado. Un solo Postgres no lo siente.
- **Lecturas**: 20.000 usuarios × 2 búsquedas/día × 20 locales × 3 días de disponibilidad = millones de cálculos de slots diarios.

**El 99% de la carga es lectura.** Eso define todo: no hacen falta microservicios, hace falta separar la ruta de lectura de la de escritura y cachear agresivamente.

**WebSocket no ayuda con la concurrencia de agendamiento.** Dos clientes peleando por el mismo slot se resuelve en el constraint de Postgres; el WebSocket solo empuja el cambio a las pantallas después.

### 12.2 Stack

| Pieza | Elección | Nota |
|---|---|---|
| Base de datos | **PostgreSQL 15+ con PostGIS y btree_gist** | La única elección no negociable |
| Backend | **Laravel 13** + Octane (FrankenPHP) | Laravel 13 salió el 17/03/2026; requiere PHP 8.3+. Bugfixes hasta Q3 2027, seguridad hasta Q1 2028 |
| Apps | **Flutter**, un solo proyecto con roles | Cliente y local en la misma base de código |
| Caché y colas | Redis | |
| Tiempo real | Laravel Reverb | Proceso aparte de la API |
| Push | Firebase Cloud Messaging + APNs Auth Key | Ver §11.5 |
| WhatsApp | WhatsApp Cloud API | Plantillas aprobadas con antelación |
| Mapas | Google Maps SDK | Registro del local y vista de búsqueda |
| Observabilidad | Sentry + logs JSON + Telescope (dev) | |

Sobre Laravel vs Node: es una recomendación **débil**. Laravel se elige por productividad (Eloquent, migraciones, Horizon, políticas, Cashier, Reverb ya resuelto), no porque PHP sea superior. Si hay más comodidad en TypeScript, NestJS hace el mismo trabajo. Lo que no conviene es mezclar los dos —Laravel para el dominio y un microservicio Node para WebSocket— porque son dos runtimes y dos despliegues para un solo desarrollador, a cambio de nada que Reverb no cubra.

El argumento "mismo lenguaje en front y back" **no aplica**: la app es Flutter (Dart). No se comparten tipos ni modelos con el cliente en ninguno de los dos casos.

Verificar que los paquetes de terceros soporten Laravel 13 antes de comprometerse.

### 12.3 Monolito modular

Con un solo desarrollador, microservicios multiplican despliegues, debugging distribuido y transacciones entre servicios a cambio de una escala inexistente. Lo que sí hace falta son fronteras internas reales.

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

**Qué es compartido y qué no.** Los modelos Eloquent viven todos juntos en `app/Models`, y las migraciones todas en `database/migrations`. No se reparten por módulo.

El esquema no es modular aunque el código lo sea: `cita` tiene claves foráneas a `local`, `usuario`, `profesional`, `recurso`, `servicio_local`, `producto` y `mascota` — cruza cinco módulos. Es una sola base de datos y una sola línea de tiempo de migraciones; separarlas en carpetas crea una frontera que la base no tiene, y obliga a numerar los archivos para que el orden global siga funcionando.

Con los modelos, la decisión es un compromiso consciente. La alternativa pura —que un módulo no importe los modelos de otro, y que `Billing` pida los datos de citas por un puerto— es más limpia en el papel, pero con Eloquent sale cara: `Cita` se relaciona con local, profesional, cliente, recurso y servicio, cada una cruzando un módulo. Con un solo desarrollador, resolver eso por puertos convierte cada pantalla en ceremonia sin comprar nada a esta escala.

**Lo que sí hay que respetar, y es lo que hace que los módulos sirvan:**

- La **lógica de negocio de un módulo vive en ese módulo**. `Billing` no implementa reglas de agendamiento ni recalcula disponibilidad; `Scheduling` no decide comisiones. Compartir modelos no es licencia para compartir reglas.
- Las reacciones entre módulos van por **evento de dominio**, no por llamada directa: `CitaCreada`, `CitaCompletada`, `CitaCancelada`, `CitaReagendada`, `TurnoModificado`, `ResenaPublicada`. `Notifications` y las proyecciones de `Scheduling` escuchan; nadie los llama.
- Los controladores traducen HTTP a caso de uso y nada más.

**El precio a pagar:** extraer `Scheduling` a un servicio propio (§12.9, etapa 3) exige deshacer esto primero. Está asumido — esa etapa llega solo si el equipo crece y los despliegues se estorban, y la mayoría de estas aplicaciones nunca sale de la etapa 0.

`Scheduling` es el único módulo donde vale la pena gastar esfuerzo de diseño. Los demás son CRUD con reglas.

### 12.4 Idempotencia

Todo endpoint de escritura recibe un header `Idempotency-Key` (UUID generado por el cliente). Se guarda clave → respuesta; si la clave repite, se devuelve la respuesta guardada sin ejecutar nada. Aplica a agendar, cancelar, registrar cobro y reseñar.

### 12.5 Caché

| Qué | TTL | Invalidación |
|---|---|---|
| Disponibilidad por (local, profesional, día) | 15 min | **Por evento**: cita creada/cancelada, turno o excepción modificada |
| Resultado de búsqueda por (geohash, filtros) | 60 s | Solo TTL |
| Perfil público del local | 1 h | Al editar el local |
| Score de ranking | — | Job nocturno, nunca en request |

La disponibilidad **no puede depender solo de TTL**: si cancelan una cita, ese slot tiene que aparecer ya.

### 12.6 Colas

Laravel Queue con Redis, separadas por prioridad:

- `critica` — expiración de holds (cada minuto), confirmaciones
- `notificaciones` — WhatsApp y push, con retry y backoff
- `proyecciones` — reconstrucción de `disponibilidad_dia`
- `batch` — ranking nocturno, liquidaciones, reportes

### 12.7 Base de datos en producción

- Réplica de lectura para búsqueda y disponibilidad cuando haga falta. Escrituras siempre al primario, y **leer del primario justo después de escribir** (el lag de replicación muestra al usuario que su cita no existe).
- PgBouncer antes de pensar en cualquier otra optimización.
- Particionado: ver §4.13.

### 12.8 Qué NO hacer

- Microservicios, Kubernetes, service mesh
- Event sourcing (suena perfecto para citas; duplica el trabajo y no se va a necesitar)
- GraphQL (con Flutter y un solo cliente, REST es más simple y cacheable)
- Multi-región
- Cualquier cosa con "distributed" en el nombre

**El enemigo real no es la escala: son los N+1.** Una pantalla de búsqueda que dispara 400 consultas duele con 50 usuarios, mucho antes de que la arquitectura importe. `preventLazyLoading()` en desarrollo desde el día uno.

### 12.9 Escalado por etapas

| Etapa | Infra | Disparador para pasar |
|---|---|---|
| 0 | 1 VPS: Laravel + Postgres + Redis + worker | Aguanta 200–500 locales |
| 1 | Postgres separado, réplica de lectura, más workers | p95 de disponibilidad > 300 ms |
| 2 | Búsqueda a su propio read store, particiones activas | > 5M citas o > 2.000 locales |
| 3 | Extraer `Scheduling` a servicio propio | Solo si el equipo crece y los despliegues se estorban |

La mayoría de las apps de este tipo nunca sale de la etapa 0.

### 12.10 Observabilidad

Sentry para errores, logs estructurados en JSON, y métricas de: latencia de la consulta de disponibilidad, tasa de colisión de slots, tasa de fallo de envíos de WhatsApp, profundidad de las colas.

No se puede arreglar lo que no se ve, y con un solo desarrollador no se puede permitir enterarse de los problemas por un cliente enojado.

---

## 13. Cumplimiento legal

Referencial. Confirmar con un abogado especializado y revisar el portal de la SPDP, que emite normativa nueva con frecuencia.

### 13.1 LOPDP

El producto construye una base de datos de personas naturales con teléfono, historial de servicios y datos de menores (cortes de niños). Aplica de lleno la **Ley Orgánica de Protección de Datos Personales** (Registro Oficial Suplemento 459, 26 de mayo de 2021; régimen sancionatorio vigente desde el 26 de mayo de 2023; reglamento de noviembre de 2023).

Ya no es hipotético: la Superintendencia cerró sus primeros procedimientos sancionatorios con multas a LIGAPRO por USD 259.644,01 y a la Federación Ecuatoriana de Fútbol por USD 194.856,16, ambos por recoger consentimiento inválido en sus aplicaciones. A LIGAPRO además le ordenaron notificar a 14.398 titulares y eliminar sus datos. El tamaño de la empresa no elimina la obligación.

Lo que hay que modelar desde el día uno, no parchar después:

- **Consentimiento registrado** con versión y timestamp, separado por finalidad: operar la cita ≠ mandar promociones (tabla `consentimiento`, §4.3)
- **Derecho de eliminación**: `usuario.anonimizado_at` — se borran datos personales y se conserva la cita anonimizada, que se necesita para las estadísticas del local
- **Política de privacidad y términos versionados**
- **Responsable / delegado de protección de datos** designado
- **Registro de transferencias**: Meta (WhatsApp) y Firebase son transferencia internacional y hay que declararlo

**Punto de riesgo específico: el cliente sombra.** La recepción registra el teléfono de una persona que no aceptó nada. Eso es exactamente el tipo de consentimiento inválido que se sancionó. El local tiene que recoger la autorización y la plataforma tiene que registrarla y poder probarla.

### 13.2 Otras obligaciones

- **Facturación electrónica (SRI)** al cobrar suscripciones — ver §10.4
- **Moderación de contenido**: con fotos y reseñas subidas por usuarios, el flujo de reportes (tabla `reporte`, §4.8) no es opcional
- **Términos para el negocio**: prohibición explícita de exportar la base de clientes de la plataforma

---

## 14. Métricas

Ordenadas por importancia real, no por vanidad:

1. **% de citas de cliente nuevo por local** — si es bajo, solo se cambió WhatsApp por app y no hay nada que cobrar
2. Citas completadas / semana
3. Retención del local (¿sigue activo a los 60 días?)
4. Tasa de no-show
5. Densidad: locales activos por zona
6. Conversión búsqueda → cita
7. Reseñas por cita completada
8. Costo de mensajería por local (margen del Pro)

Descargas y usuarios registrados no son métricas.

---

## 15. Alcance v1

### 15.1 Dentro

- Registro de negocio + 1 local: servicios desde catálogo con precio, horarios, fotos
- Registro de profesional, asignación a local y turnos (incluye multi-local)
- Roles: propietario, profesional, recepción, cliente — matriz de §3.2
- Cliente sombra para walk-ins, con reclamo de cuenta por OTP
- Búsqueda por cercanía con filtros (vertical, servicio, precio, amenidades, disponibilidad)
- Perfil de local: servicios, precios, amenidades, profesionales, reseñas, fotos
- Perfil y portafolio del profesional
- Agendamiento con slots reales (habilidad + recurso + traslado + concurrencia)
- Agenda del local con walk-ins y cambios de estado
- Historial de citas del cliente, favoritos, nota del local sobre el cliente
- Reagendamiento
- Push en las tres situaciones de la app + deep link; WhatsApp para OTP, confirmación y recordatorio de 2 h
- Reseña post-cita con ventana de 14 días
- Campo `cliente_nuevo` en el booking
- Registro de consentimientos y derecho de eliminación

### 15.2 Fuera de la v1

- Pagos, abonos y cualquier flujo de dinero por la plataforma
- Chat cliente ↔ local
- Múltiples sucursales
- Fidelización / puntos
- Liquidación de comisiones (es el gancho del Pro, va al encender el plan)
- Vertical mascotas activa (modelada, no activada)
- Citas recurrentes
- Estadísticas completas
- Cualquier cosa con IA

---

## 16. Decisiones pendientes

Ninguna es técnica; todas cambian el producto.

1. **¿El perfil del profesional es público y buscable?** Es la función más fuerte del producto (la lealtad en barbería es al barbero, no al local) y el mayor riesgo (le da al barbero la herramienta para irse con la cartera del dueño que paga). Recomendación: público, reseña separada, pero **sin** notificación automática a sus clientes cuando se muda.
2. **¿Reseña del profesional además del local?** Recomendación: sí, separadas.
3. **¿Precio por profesional?** (senior cobra más). Soportado con `habilidad.precio_override`, pero complica la UI. Recomendación: no en la v1.
4. **Comisión sobre precio lleno o con descuento?** Si el dueño hace promo del 30% y paga comisión sobre el precio rebajado, el barbero siente que le bajaron el sueldo por una decisión ajena. **Decidir ahora**, no con 40 locales: cambiarlo después es cambiarle la plata a la gente.
5. **¿Abono en v2?** El dato decide — ver §10.3.
6. **¿Grooming de mascotas cuándo?** Modelado ya; activar después de validar barberías.

---

## 17. Antes de programar

Sigue pendiente lo más importante, y no es código: **10 entrevistas con barberías de la zona elegida.**

Preguntas: cómo manejan la agenda hoy, con qué frecuencia tienen no-shows, cómo cuadran las comisiones, qué les molesta, si pagarían por resolverlo y cuánto.

- Si 7 de 10 dicen que las comisiones son un dolor de cabeza → hay producto, y la prioridad es la herramienta de gestión
- Si dicen "con WhatsApp estoy bien" → se ahorraron tres meses

Este documento está diseñado para ser correcto. Las entrevistas dicen si vale la pena construirlo, y en qué orden.

**La red de seguridad:** la liquidación de comisiones sale casi gratis de este esquema y es vendible **aunque el marketplace no despegue**. El marketplace es la parte que no se controla; la herramienta de gestión sí.
