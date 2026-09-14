# FullPinta — Plan de implementación

Documento de trabajo. Se marca lo hecho a medida que avanza y se actualiza cuando una decisión cambia el rumbo.

La fuente de verdad del producto sigue siendo [`fullpinta-especificacion.md`](fullpinta-especificacion.md). Este archivo solo dice **en qué orden** se construye y **qué falta**.

**Regla de orden:** nada se empieza si lo que lo bloquea no está listo. Las fases están ordenadas por dependencia real, no por importancia.

---

## Fase 0 — Esquema y modelos ✅

- [x] Entorno: PostgreSQL 17 + PostGIS 3.5 + btree_gist + Redis en Docker
- [x] API pura: Sanctum por tokens, `/api/v1`, respuestas JSON, sin sesión ni CSRF
- [x] Middleware de idempotencia (§12.4) — reclama la clave antes de ejecutar
- [x] Las 43 tablas del §4, con los 3 constraints críticos del §4.11
- [x] 41 modelos con relaciones, casts y scopes
- [x] Modo estricto de Eloquent fuera de producción (§12.8)
- [x] Tests: constraints críticos, tokens, salud, concordancia modelo↔esquema

---

## Fase 1 — Datos base ✅

- [x] **1.1** Seeder de `servicio_categoria` — 17 categorías en 4 verticales (§4.5)
- [x] **1.2** Seeder de `catalogo_servicio` — 25 servicios con duración base y tipo de recurso
- [x] **1.3** Seeder de `amenidad` — 27 amenidades en 6 categorías (§4.4)
- [x] **1.4** `DatabaseSeeder` idempotente (`updateOrCreate`): sirve para publicar cambios del catálogo sobre una base con datos
- [x] **1.5** Factories para las 41 tablas, con estados de dominio (`sombra`, `holdVencido`, `walkIn`, `pocoConfiable`, `deProfesional`…)
- [x] **1.6** Tests: 82 de factories, 6 de semillas

Los slugs del catálogo llevan la vertical por delante (`estetica-corte-de-dama`) para que no colisionen cuando dos verticales tengan un servicio homónimo.

---

## Fase 2 — Identidad y acceso ✅

- [x] **2.1** Servicio de OTP: generación, expiración (5 min), límite de intentos (5), rate limit por teléfono (3 / 10 min). Tabla `otp` — adición al esquema, no está en el §4 original; documentado en el modelo
- [x] **2.2** Registro e inicio de sesión por teléfono → token de Sanctum. Un solo par de endpoints para ambos casos
- [x] **2.3** Cliente sombra: reclamo de cuenta por OTP heredando historial (mismo `usuario_id`, sin duplicar fila)
- [x] **2.4** Resolución de contexto: `negocio_miembro` y `asignacion` **vigentes** → `GET /auth/contexto`, contrato completo en `docs/api-referencia.md`
- [x] **2.5** `App\Support\Auth\ContextoAcceso` con la matriz del §3.2. **No** se crearon Policies de Laravel por modelo todavía — una Policy sin controller que la use es código muerto; se crean en la Fase que introduce cada controller, delegando en este servicio
- [x] **2.6** Consentimientos por finalidad (`OtorgarConsentimiento` / `RevocarConsentimiento`, cada uno con su endpoint) y derecho de eliminación (`AnonimizarUsuario` vía `DELETE /cuenta`)
- [x] **2.7** Tests: 34 casos — OTP completo, reclamo de cuenta con historial intacto, multi-rol, cada celda de la matriz, consentimientos, eliminación de cuenta

**Supuesto documentado a confirmar con producto:** la matriz del §3.2 no tiene columna para el rol `admin` (existe en el schema de `negocio_miembro` pero no en la tabla de permisos). Se asumió que `admin` = `propietario` en todo **excepto** gestión de suscripción/facturación, que queda exclusiva al dueño legal (`negocio.propietario_id`). Ver docblock de `ContextoAcceso`.

Nota: el envío real por WhatsApp depende de la Fase 9. Hasta entonces, `LogEnviadorOtp` escribe al log (nunca usar en producción) y los tests usan `FakeEnviadorOtp` (código en memoria, sin parsear logs).

**Contrato de API:** cada endpoint de esta fase está documentado en [`api-referencia.md`](api-referencia.md) — mantenerlo actualizado es parte de terminar cada fase, no un extra.

**2026-09-17, adición post-v1: login con Google y con correo/contraseña.** El usuario pidió sumar estos dos métodos sin renunciar a la regla de que el teléfono es obligatorio y se verifica siempre por OTP — se actualizó `context/fullpinta-especificacion.md` §4.3 en consecuencia (antes decía "no hay contraseña en ningún flujo del cliente"). Cambios:

- Migración nueva (no se tocó la del esquema base): `usuario` gana `google_id` (`varchar UNIQUE NULL`, el `sub` del token — nunca el email, que puede cambiar de lado de Google). `telefono` sigue exactamente igual (`NOT NULL UNIQUE`).
- `POST /auth/google` (`{id_token, telefono}`): resuelve por `google_id` → login; si no, por `email` de una cuenta existente → **enlaza** (el token de Google ya prueba la titularidad del correo, enlazar es seguro); si no, crea cuenta nueva con el teléfono del body y `telefono_verificado: false`.
- `POST /auth/registro` / `POST /auth/login` (`{nombre, email, password, telefono}` / `{email, password}`): a diferencia de Google, **nunca enlaza por coincidencia de email** — una contraseña elegida por cualquiera no debe poder tomar una cuenta ajena. Rate limit por email igual que el OTP por teléfono.
- La verificación del teléfono para las cuentas creadas por Google o por correo es la **misma** que ya existía (`VerificarOtp` ya sabía marcar `telefono_verificado: true` sobre una cuenta existente sin crear una fila nueva) — no se construyó un mecanismo de verificación aparte.
- `password_hash` conserva su significado original (`NULL` = cliente sombra sin reclamar): OTP y Google siguen asignando un hash aleatorio e inutilizable; solo el registro por correo guarda ahí un hash real.
- Puerto `VerificadorTokenGoogle` (`GoogleTokeninfoVerificador`/`FakeVerificadorTokenGoogle`), mismo patrón que `EnviadorOtp` — verifica contra el endpoint público de Google (`Http` facade, sin SDK ni dependencia nueva). Documentado en el propio código que `tokeninfo` no es para volumen alto de producción; la alternativa (verificar JWKS) requeriría una librería, diferida hasta que el volumen lo justifique.
- `LimitarSesionesActivas` se extrajo de `VerificarOtp` para compartirse entre los tres métodos de login, en vez de triplicar la lógica de "máximo N dispositivos".
- Tests: 13 casos nuevos (`GoogleLoginTest`, `EmailLoginTest`), incluido el flujo completo registro-por-correo → verificar por OTP → `telefono_verificado: true` sin duplicar cuenta.

---

## Fase 3 — Directorio y catálogo del local ✅

CRUD del dueño. Es el módulo más plano del sistema.

- [x] **3.1** Negocio y local (alta, edición, estado, ubicación con PostGIS vía cast `App\Casts\Ubicacion`)
- [x] **3.2** Horarios del local, con jornada partida
- [x] **3.3** Amenidades del local (catálogo público + sync por local, con `detalle` por pivot)
- [x] **3.4** Servicios del local: precio, duración, buffer, comisionable, precios por tamaño (sync)
- [x] **3.5** Fotos del local
- [x] **3.6** Productos
- [x] **3.7** Solicitudes al catálogo maestro (sin aprobar/rechazar — necesita panel de soporte de plataforma, no existe aún)
- [x] **3.8** Tests: 63 casos — recepción ve pero no edita, propietario/admin sí, extraños 403 en todo

**Decisiones de esta fase, para no repetirlas:**

- **Servicios en `Application/`: un archivo por modelo** (`LocalService`, `NegocioService`, `HorarioLocalService`, `AmenidadService`, `ServicioLocalService`, `ProductoService`, `SolicitudCatalogoService`), no una clase por acción. Se abandonó el patrón anterior (una clase por acción) a mitad de la fase — ver skill `modulo`.
- **Controladores**: exactamente un `try`/dos `catch` vía `$this->ejecutar(...)` (trait `EjecutaServicio`), que llama a un solo método de un solo servicio. Reglas de negocio se lanzan con el helper `throw_validacion()`. Ver skill `endpoint`.
- **Rutas**: `Route::apiResource(...)->shallow()` donde el recurso calza con CRUD clásico, en vez de listar cada verbo a mano — cuidado con `->parameters()` cuando el plural en inglés no coincide con el nombre en español (`locales` → Laravel arma `{locale}`, no `{local}`).
- **Trampa real y ya corregida dos veces**: una columna con `DEFAULT` en Postgres que el `FormRequest` marca opcional necesita repetirse explícita en el `crear()` del servicio — si no, el modelo recién creado devuelve `null` en la respuesta en vez del default real. Ver skill `migracion`.
- **`lang/es/` no existía.** Laravel moderno no trae español por defecto; se publicó y tradujo `validation.php`/`auth.php` a mano (sin agregar dependencias nuevas).
- Cast `App\Casts\Ubicacion` resuelve `geography(Point,4326)` como `['lat'=>float,'lng'=>float]` en ambas direcciones, decodificando el WKB hexadecimal de Postgres a mano (formato fijo y acotado, no hace falta librería).
- **2026-09-15, corrección retroactiva (1/2):** `servicio_categoria` no tenía `id` (PK compuesta `vertical, codigo`) y `amenidad.categoria`/`catalogo_servicio.vertical`/`solicitud_catalogo.vertical` repetían el mismo `varchar` + `CHECK` en varias tablas. Se normalizó: nuevas tablas `vertical` y `amenidad_categoria` (ambas `id, codigo, nombre, activo`), `servicio_categoria` con `id` propio (único `vertical_id, codigo`), y las FK compuestas pasaron a FK simples por `id`. El contrato de la API no cambió (`vertical`/`categoria_codigo`/`categoria` se siguen exponiendo como código de texto en las respuestas, resueltos desde la relación) — solo el esquema interno. Se hizo editando las migraciones base directamente + `migrate:fresh`, no con una migración nueva encima, porque el esquema base aún no tenía datos reales en producción.
- **2026-09-15, corrección retroactiva (2/2), auditoría completa del esquema a pedido del usuario:** se revisaron las 20 tablas del esquema buscando el mismo patrón (un `varchar`+`CHECK` repetido en más de una tabla, o una tabla sin `id`). Se encontraron y corrigieron 4 casos más — **`tamano_mascota`** (`mascota.tamano` + `servicio_local_tamano.tamano`), **`tipo_recurso`** (`catalogo_servicio.tipo_recurso` + `recurso.tipo` — el más valioso: antes nada garantizaba que el agendamiento casara el tipo de servicio con el tipo de recurso físico), **`plan`** (`negocio.plan` + `suscripcion.plan`) y **`notificacion_categoria`** (`preferencia_notificacion.categoria` + `notificacion.categoria`, mismo módulo) — todas con la misma forma `id, codigo, nombre, activo`. Además, regla final fijada con el usuario: **uuid PK siempre, sin excepción**, ni para claves naturales — `cliente_perfil` (antes PK `usuario_id`), `idempotencia` (antes PK `clave`) y `plantilla_whatsapp` (antes PK `nombre`) pasaron a tener `id` uuid, con su clave natural como columna `UNIQUE` normal. Mismo método que la corrección (1/2): migraciones base editadas directo + `migrate:fresh`, contrato de API sin cambios de forma (los códigos se siguen exponiendo como texto, resueltos vía relación con `->with(...)` para no romper `preventLazyLoading()`). Ver skill `migracion`, sección "Un valor repetido en varias filas o varias tablas es tabla de parámetros".

---

## Fase 4 — Personal, turnos y recursos ✅

- [x] **4.1** Profesional y portafolio de fotos
- [x] **4.2** Asignaciones a local, con vigencia
- [x] **4.3** Turnos recurrentes — el constraint rechaza traslapes, hay que traducir `23P01` a un error legible
- [x] **4.4** Overrides por fecha (`turno_fecha`)
- [x] **4.5** Recursos (una fila por unidad física)
- [x] **4.6** Habilidades — qué profesional hace qué servicio
- [x] **4.7** Excepciones: local, profesional o recurso
- [x] **4.8** Tests: 38 casos — traslape de turnos (mismo local y entre locales distintos), duplicar habilidad, override sin cancelar sin horas, las tres variantes de excepción

**Decisiones de esta fase, para no repetirlas:**

- **`ProfesionalPolicy` no compara contra un local fijo.** Un profesional puede trabajar en varios locales a la vez (§4.6), así que `ver`/`actualizar` se resuelven como "¿administra el usuario AL MENOS UN local donde este profesional tiene una `asignacion` vigente?" — nunca "¿es dueño del local de este profesional?" porque no hay uno solo.
- **`turno`/`asignacion` desnormalizan `profesional_id`/`local_id`** solo para habilitar el `EXCLUDE USING gist` (§4.11); se escriben siempre desde la asignación real en el servicio, nunca desde el request.
- **`turno_sin_traslape` es sobre `profesional_id` solo**, igual que `cita_profesional_sin_traslape` (§4.11): un profesional no puede tener turnos encimados aunque sean de locales distintos. Verificado con test explícito (dos locales, mismo profesional, mismo horario → 422).
- **Constraint `EXCLUDE` capturado y traducido**: `TurnoService` atrapa `QueryException` con SQLSTATE `23P01` y lo convierte en `throw_validacion()` — el mismo patrón que se documentó en Fase 3 para otros errores de base, ahora aplicado a un constraint de exclusión real.
- **`Excepcion` tiene tres métodos de creación (`crearParaLocal`/`crearParaProfesional`/`crearParaRecurso`), no uno genérico**: cada uno expresa una intención distinta y el nombre ya lo dice, en vez de un parámetro "cuál de los tres". Mismo criterio en el controller (`storeLocal`/`storeProfesional`/`storeRecurso`) y en el `destroy` compartido, que resuelve el dueño real vía `match` sobre cuál FK está lleno.
- **Corrección retroactiva de esquema (2026-09-15)**: normalización de `vertical` y `amenidad.categoria` a tablas de parámetros — ver la nota en Fase 3 y la skill `migracion` ("Un valor repetido en varias filas o varias tablas es tabla de parámetros").

---

## Fase 5 — Motor de disponibilidad ✅

**El 90% de la dificultad del sistema y lo único que no se puede improvisar después.** Se construyó con su suite de tests desde el principio, no después.

- [x] **5.1** Cálculo de slots con los 10 filtros del §5.1, granularidad de 15 min (`DisponibilidadService::slots()`/`ventanasLibres()`)
- [x] **5.2** Traslado entre locales (`traslado_min`) — no lo valida ningún constraint
- [x] **5.3** Overrides de `turno_fecha` aplicados después de los recurrentes
- [x] **5.4** Caché por (local, profesional, día), 15 min, **invalidada por evento** (§12.5)
- [x] **5.5** Proyección `disponibilidad_dia` reconstruida por job (`ReconstruirDisponibilidadDia`, cola `proyecciones`)
- [x] **5.6** Suite obligatoria del §5.7 — 10 tests en `DisponibilidadTest` + 1 en `ConcurrenciaCitaTest`:
  - [x] solapamientos
  - [x] turnos multi-local
  - [x] traslado entre locales
  - [x] recursos ocupados
  - [x] excepciones (local, profesional, recurso)
  - [x] cruces de medianoche
  - [x] holds vencidos con el worker caído
  - [x] **20 peticiones paralelas al mismo slot: gana exactamente una**

Regla que gobierna todo esto: **se cachea para mostrar, nunca para decidir.**

**Decisiones de esta fase, para no repetirlas:**

- **Fase 5 es de solo lectura.** El motor calcula slots; no crea citas de verdad. Se agregó `CitaService::reservar()` — un único método mínimo (solo el `INSERT` del hold en `reservada`, sin precios, sin `cita_item`, sin máquina de estados) — únicamente para poder escribir el test de concurrencia real del §5.7 contra Postgres de verdad. Fase 6 (Agendamiento) expande ese método, no lo reescribe.
- **`ventanasLibres()` vs `slots()`**: los filtros que NO dependen del servicio pedido (horario del local, turno+overrides, excepciones de local/profesional, citas existentes con traslado, lead time/horizonte) viven en `ventanasLibres()`, que es la unidad que se cachea por (local, profesional, día) — coincide exactamente con la tabla de caché del §12.5. Los que sí dependen del servicio (habilidad, duración total, recurso libre) se aplican encima, sin cachear.
- **`turno_fecha` es siempre por-local**: cada override (`cancela`/`reemplaza`/`extra`) afecta solo al `(profesional, local, fecha)` de esa fila — nunca "mueve" a un profesional de un local a otro. "Este sábado no voy a Urdesa, voy a Alborada" se modela con DOS filas. La especificación no lo detalla más allá de "se aplican después de los recurrentes"; esta interpretación quedó documentada en el código (`DisponibilidadService::ventanasTurno()`).
- **Supuesto de multi-servicio**: la duración total de una cita con varios servicios es `sum(duracion_min) + max(buffer_min)`, y todos comparten el mismo tipo de recurso requerido (o ninguno) — la especificación no cubre composición multi-recurso, está fuera del alcance de la v1.
- **Huso horario de `time` sin zona** (`horario_local.abre/cierra`, `turno(_fecha).entra/sale`): se interpretan en UTC directo, igual que el resto de la base (CLAUDE.md), sin conversión — Ecuador continental es un solo huso sin horario de verano, así que no hace falta más.
- **Eventos de dominio nuevos, disparados desde Staffing (Fase 4), consumidos por Scheduling**: `TurnoModificado` (turno/turno_fecha) y `ExcepcionModificada` — exactamente los nombres que ya usaba §12.3 de la especificación. `InvalidarCacheDisponibilidad` (en Scheduling) escucha ambos y además despacha `ReconstruirDisponibilidadDia` a la cola `proyecciones` para el rango de días afectado (tope duro 90 días). Los servicios de Staffing no se tocaron en su lógica, solo se les agregó el `event(...)` al final — sus 38 tests no cambiaron.
- **Trampa real de testing — `RefreshDatabase` es incompatible con procesos hijos**: el test de concurrencia lanza servidores `php -S` reales (procesos de sistema operativo separados, no hilos) porque el servidor embebido de PHP es de un solo worker en este Windows/Laragon (el modo multi-worker usa `fork()`, inexistente fuera de POSIX). Esos procesos abren su propia conexión a Postgres y **no pueden ver una transacción sin confirmar** de otro proceso — así que `ConcurrenciaCitaTest` NO usa `RefreshDatabase`, crea sus datos con `COMMIT` real y los borra a mano en `tearDown()`, en el orden que exigen las FK. Documentado también en la skill `migracion` si se repite este patrón.
- **Trampa real — efectos colaterales de factories que arman su propia cadena**: `CatalogoServicio::factory()->create(['tipo_recurso_id' => X])` igual ejecuta la resolución/creación por defecto de un `TipoRecurso` "silla" dentro de `definition()` **antes** de que el override lo pise (PHP evalúa el array completo antes de que Eloquent aplique el merge) — inofensivo bajo `RefreshDatabase` (se revierte igual), pero deja basura permanente en un test sin transacción. Mismo problema con `Local::factory()->create()`, que arrastra un `Negocio::factory()` (y ESE arrastra un `Usuario::factory()` dueño) si no se le pasa `negocio_id`. En pruebas sin `RefreshDatabase`, hay que rastrear y borrar toda la cadena implícita, no solo lo que se creó explícitamente.

---

## Fase 6 — Agendamiento ✅

- [x] **6.1** Hold: cita `reservada` con `expira_at = now() + 10 min` (ya existía desde Fase 5, `CitaService::reservar()`)
- [x] **6.2** Camino optimista — capturar `23P01` y devolver 409 `slot_ya_ocupado`, sin locks (ídem)
- [x] **6.3** Precios y comisiones **congelados** en `cita_item` y `cita_producto`
- [x] **6.4** Máquina de estados del §6, con `cita_evento` en cada transición — una clase por transición en `Application/Transiciones/`
- [x] **6.5** Reagendamiento vía `reagendada_de_id`, sin penalizar al cliente
- [x] **6.6** Walk-ins desde recepción (`canal = 'local'`)
- [x] **6.7** `cliente_nuevo` (calculado al agendar) y mantenimiento de `cliente_local` (actualizado al completar, no al agendar — un hold que expira no cuenta como visita)
- [x] **6.8** Job de expiración de holds (`ExpirarHoldsVencidos`, cola `critica`, cada minuto) — primer uso del scheduler (`bootstrap/app.php`) en el proyecto
- [x] **6.9** Lista de espera y conversión al liberarse un cupo (`EsperaService`)
- [x] **6.10** Eventos de dominio: `CitaCreada`, `CitaConfirmada`, `CitaIniciada`, `CitaCompletada`, `CitaCancelada`, `CitaReagendada` — los 4 nombrados en §12.3 más 2 adicionales (Confirmada/Iniciada) para que "cada transición reprograma notificaciones" tenga de dónde escuchar
- [x] **6.11** Tests de cada transición y de los casos borde del §5.6 — 28 tests nuevos en `tests/Feature/Scheduling/`

**Decisiones de esta fase, para no repetirlas:**

- **"Cancelada_tarde" (§5.6) NO es un `estado` nuevo.** El §6 no lo lista entre los terminales — es una clasificación derivada. Cancelar el CLIENTE dentro de `local.politica_cancelacion_horas` sigue siendo `estado = cancelada_cliente`, pero incrementa `cliente_perfil.cancelaciones_tardias` (columna que ya existía desde Fase 2 para exactamente esto). Mismo criterio para el umbral de 3 no-shows → `requiere_confirmacion = true`: columnas existentes, sin esquema nuevo.
- **`CitaCancelada` es un solo evento para 4 casos** (`cancelada_cliente`/`cancelada_local`/`no_show`/`expirada`, distinguidos por una propiedad `estado`) — la especificación no nombra un evento por cada uno, y las cuatro comparten la misma consecuencia real (liberar el slot, avisar a la lista de espera).
- **`cliente_local` se actualiza al COMPLETAR, no al agendar.** `cliente_nuevo` (el flag en `cita`) sí se calcula al agendar, contra el estado de `cliente_local` en ese momento — pero el contador (`total_citas`, `ultima_cita_at`) solo avanza cuando la visita realmente ocurrió; un hold que expira o se cancela no debe contar como visita.
- **Reagendar reutiliza `CitaService::reservar()` tal cual**, no duplica su lógica — la cita nueva pasa por el mismo camino optimista y la misma regla de `requiere_confirmacion` que cualquier cita agendada de cero. Se le agregó `reagendada_de_id` como campo opcional que `reservar()` simplemente pasa a `Cita::create()`.
- **`Idempotency-Key` solo en los verbos que el §12.4 nombra explícitamente**: agendar (`store`, `walk-in`) y cancelar. `confirmar`/`iniciar`/`completar`/`no-show`/`reagendar` no lo exigen — no están en esa lista, y agregarlo a todo sin que la especificación lo pida es inventar contrato.
- **Walk-ins con cliente nuevo**: Identity todavía no tiene un endpoint de "cliente sombra". Se resolvió con un `Usuario::firstOrCreate()` de una sola línea dentro de `CitaService` (sin OTP, sin consentimientos) para no bloquear esta fase ni invadir la frontera de Identity — documentado como decisión de alcance, a revisar si Identity construye ese flujo completo más adelante.
- **Primer uso del scheduler del proyecto** (`bootstrap/app.php`, `->withSchedule(...)`) — antes no existía ningún `Schedule::` en el código. Producción necesita el cron estándar de Laravel (`* * * * * php artisan schedule:run`), fuera del alcance de este entorno de desarrollo.
- **Docker se había caído entre sesiones** (Postgres/Redis) — hubo que relevantar Docker Desktop a mano antes de poder correr los tests de esta fase. Si un test falla con "Connection refused" al puerto 5433/6380, revisar primero si Docker está corriendo.

---

## Fase 7 — Búsqueda y perfiles públicos ✅

- [x] **7.1** Búsqueda por cercanía con índice GiST (§8) — `BusquedaLocalService` (Directory), query builder crudo sobre `ST_DWithin`/`ST_Distance`
- [x] **7.2** Filtros: vertical, servicio, precio, amenidades, disponibilidad, abierto ahora
- [x] **7.3** Caché de resultados por (coordenadas redondeadas + filtros), 60 s
- [x] **7.4** Perfil público del local, caché 1 h (`LocalService::perfilPublico()`, invalidado en `actualizar`/`activar`/`pausar`)
- [x] **7.5** Perfil y portafolio del profesional (`ProfesionalService::perfilPublico()`, Staffing)
- [x] **7.6** Historial de citas (`GET /mis-citas`, Scheduling) y favoritos (`FavoritoService`, Identity)
- [x] **7.7** Privacidad del §3.3 en `CitaResource` — `cliente_telefono` solo desde `confirmada`
- [x] **7.8** Tests: 19 casos nuevos — búsqueda (5), perfil público de local (3), perfil público de profesional (2), historial (2), favoritos (3), privacidad del teléfono (2), más los que ya cubrían N+1 vía `preventLazyLoading()`

**Decisiones de esta fase, para no repetirlas:**

- **No existe módulo "Search" (§12.3).** La búsqueda y el perfil público de `Local` viven en Directory (dueño del modelo), el perfil público de `Profesional` en Staffing, el historial de citas en Scheduling y los favoritos en Identity — cada cosa vive donde ya vive el dato que expone, nunca en un módulo nuevo transversal.
- **Sin geohash real.** No hay librería instalada; se redondean `lat`/`lng` a 2 decimales (~1 km) combinado con un hash de los demás filtros como clave de `Cache::remember` — sustituto pragmático, documentado como tal en `BusquedaLocalService`, no un geohash de verdad.
- **`BusquedaLocalService` devuelve un `Collection` plano, no un `LengthAwarePaginator`.** La paginación automática de Laravel envuelve la respuesta bajo `data`/`links`/`meta` incluso con `JsonResource::withoutWrapping()` activo — eso rompería la convención del proyecto (arrays JSON planos, sin envoltorio) que ya usa cada endpoint de listado. `page`/`limit` se aplican a mano (`forPage()`) sin exponer metadatos de paginación.
- **`GROUP BY l.id` sin listar cada columna**: Postgres lo permite porque son funcionalmente dependientes de la PK de `local` — incluye `ST_Distance(l.ubicacion, :punto)`, que depende solo de columnas de esa misma fila.
- **404, no 403, en ambos perfiles públicos** si el local no está `activo` o el profesional tiene `perfil_publico = false` — no hay que confirmarle al público que el recurso existe pero está oculto.
- **`FavoritoService` es un archivo por modelo** (como Directory/Staffing/Scheduling), no una clase por acción — a pesar de que Identity ya tenía el patrón "una clase por acción" (`SolicitarOtp`, `VerificarOtp`...) para sus casos de uso existentes. Con solo dos operaciones (`alternar`/`listar`) sobre un solo modelo, sigue la convención mayoritaria del proyecto en vez de la local de Identity.
- **El resto de la privacidad del §3.3 ya estaba satisfecha sin código nuevo**: "ocupado/otro compromiso" entre locales lo garantiza el diseño de `DisponibilidadService` desde la Fase 5 (nunca explica por qué un slot no está libre); el cambio de local de un profesional no notifica a nadie porque Notifications no existe todavía; las comisiones ya estaban gateadas por `ContextoAcceso::puedeVerComisionesDeTodos` desde la Fase 2.

---

## Fase 8 — Reseñas y reputación ✅

- [x] **8.1** Reseña post-cita: solo `completada`, ventana de 14 días, una por cita (`ResenaService::crear()`, apoyado en `Cita::admiteResena()` ya existente desde la Fase 0)
- [x] **8.2** Respuesta del local (`POST /resenas/{resena}/responder`, autorización vía `ContextoAcceso::puedeResponderResenas()`, ya existente desde la Fase 2)
- [x] **8.3** Promedio bayesiano (§7.1) y comportamiento con pocos datos (§7.2) — el bayesiano ya "diluye" un local de pocas reseñas hacia el promedio global, sin necesidad de un umbral aparte para "encender las estrellas" en la v1
- [x] **8.4** Job nocturno de `score_ranking` (§7.3) — `RecalcularScoreRanking`, cola `batch`, `dailyAt('02:00')`, nunca en request
- [x] **8.5** Reportes y moderación — solo creación (`POST /reportes`); sin panel de resolución (ver decisiones)
- [x] **8.6** Tests: 18 casos nuevos — reseñas (9), reportes (3), job de ranking (5)

**Decisiones de esta fase, para no repetirlas:**

- **La fórmula de `score_ranking` combinando los factores del §7.3 es una interpretación explícita, no un número del documento.** El §7.1 da la fórmula bayesiana exacta (`m=10`); el §7.3 solo *lista* factores sin pesos. Se implementó: bayesiano (0-5, dominante) + actividad (citas completadas en 30 días, `min(n,30)/30`) + confiabilidad (`1 - cancelada_local/total_citas_30d`, penaliza cancelaciones del LOCAL, nunca del cliente) + completitud de perfil (fotos/servicios/horarios, peso 0.5) + bonus verificado (+0.1). Documentado en el docblock de `RecalcularScoreRanking` — revisar con producto si hace falta calibrar los pesos con datos reales.
- **"Tiempo de respuesta a solicitudes" (§7.3) no se modeló.** El esquema no tiene noción de "solicitud pendiente de aprobación" — el agendamiento es inmediato vía slot, nunca por aprobación del local. Se omitió del cálculo en vez de inventar una proxy sin respaldo en el dominio.
- **Sin endpoint de moderación** (resolver/descartar reportes, cambiar `estado` de una reseña a `en_revision`/`oculta`): no existe panel de soporte de plataforma, mismo precedente que `SolicitudCatalogo` (Fase 3) — se revisa a mano por ahora.
- **`ResenaPolicy` nueva** (autodescubierta por convención, como `CitaPolicy`/`LocalPolicy`): `crear` compara `cliente_id` directo (igual que `CitaPolicy::esElCliente`), `responder` reusa `ContextoAcceso::puedeResponderResenas()` — ya existía desde la Fase 2, escrita en anticipo de esta fase.
- **`GET /locales/{local}/resenas` (staff) muestra TODOS los `estado`**, a diferencia del perfil público (Fase 7) que solo muestra `publicada` — el staff necesita ver lo que está oculto/en revisión para saber qué está pasando con su local.

---

## Fase 9 — Notificaciones ✅

- [x] **9.1** Registro y ciclo de vida de `device_token` (§11.10) — `POST /dispositivos` (upsert por `token`), `DELETE /dispositivos/{deviceToken}`
- [x] **9.2** Preferencias por categoría — `GET`/`PUT /mis-preferencias-notificacion`
- [x] **9.3** Envío por FCM + APNs, con payload del §11.4 — puerto `EnviadorPush` listo, implementación real diferida (bloqueador externo: sin proyecto de Firebase)
- [x] **9.4** WhatsApp Cloud API con plantillas aprobadas — puerto `EnviadorWhatsApp` listo, implementación real diferida (bloqueador externo: sin plantillas aprobadas por Meta)
- [x] **9.5** La matriz de eventos del §11.2 que no dependen de Billing (8 de 9 filas — ver decisiones)
- [x] **9.6** Anti-duplicados (`UNIQUE` + `tipo_evento` con sufijo de rol/id), cancelar y reprogramar al reagendar
- [ ] **9.7** Presupuesto por local y degradación a solo push — diferido (ver decisiones)
- [x] **9.8** WebSocket para la agenda del local — puerto `EnviadorWebSocket` y canal `websocket` en el esquema listos, Reverb real diferido (paquete no instalado)
- [x] **9.9** Colas separadas por prioridad (§12.6) — `notificaciones` para programar/enviar, ya usadas junto con `critica`/`proyecciones`/`batch` de fases anteriores
- [x] **9.10** Tests: 23 casos nuevos — dispositivos (4), preferencias (2), `NotificacionService` vía eventos reales (6), recordatorios (3), solicitudes de reseña (3), envío (5)

**Decisiones de esta fase, para no repetirlas:**

- **Mismo patrón que el OTP (Fase 2) para lo que no se puede conectar todavía**: puertos `EnviadorPush`/`EnviadorWhatsApp`/`EnviadorWebSocket` (`app/Modules/Notifications/Application/Contracts/`) con implementaciones `LogEnviador*` (loguean) y `FakeEnviador*` (en memoria, para tests) — el día que existan las credenciales, solo cambia el binding en `NotificationsServiceProvider::register()`, ningún caso de uso se toca.
- **Diferido explícitamente, no inventado**: presupuesto de WhatsApp por local/mes (§11.3 — no hay campo en el esquema para el límite y depende del plan contratado, Billing no existe todavía); Reverb real (paquete no instalado, cambiar dependencias requiere aprobación); FCM/APNs/WhatsApp Cloud API reales (bloqueadores externos ya trackeados); "suscripción por vencer" (última fila del §11.2, depende de `suscripcion` con lógica de aplicación, Fase 10).
- **`tipo_evento` codifica el destinatario cuando dos roles distintos reciben el mismo evento** (`cita_creada_cliente` vs `cita_creada_profesional`, `cita_cancelada_cliente` vs `cita_cancelada_profesional`) — necesario porque `UNIQUE(cita_id, tipo_evento, canal)` no incluye `usuario_id`. Cuando puede haber **más de un** destinatario del mismo rol para el mismo evento (varios propietarios/admins de un local respondiendo a una reseña, o varios clientes en la misma lista de espera para el mismo cupo), el sufijo es el id del destinatario o de la fila de origen (`resena_nueva_{usuario_id}`, `cupo_liberado_{espera_id}`), no el rol.
- **Postgres aborta la transacción completa ante un `QueryException` no recuperado con `SAVEPOINT`.** `NotificacionService::crear()` envuelve cada intento en su propio `DB::transaction()` (Laravel usa `SAVEPOINT` automáticamente si ya hay una transacción abierta, como la de `RefreshDatabase` en tests o la de `CitaService::crear()`) — capturar la violación de `UNIQUE` sin esto deja la conexión en `25P02` ("transacción abortada") para cualquier consulta posterior en el mismo request. Se descubrió con un test real, no en teoría.
- **`NotificacionCategoria::programar()` se autocura si la categoría no existe** (crea la fila con ese `codigo`), aunque el módulo tenga su propio seeder desde la Fase 1 — los listeners de notificación reaccionan a eventos de dominio que disparan MUCHOS tests de otros módulos (Scheduling, Reviews, Staffing) que no seedean `notificacion_categoria`. Mismo patrón que ya usan las factories de otras tablas de parámetros (`CatalogoServicioFactory` con `TipoRecurso`).
- **Sin "ventana de detección" en los jobs de recordatorio/reseña**: se reescanea todo lo `confirmada`/`completada` sin la notificación correspondiente en cada corrida (cada 10 min) — el `UNIQUE` hace que reprogramar dos veces sea gratis, más simple y más robusto que llevar un registro de "qué ya se detectó". Acotado a los últimos 14 días para "pedir reseña" (mismo plazo que `Cita::admiteResena()`), para no reescanear el historial completo para siempre.
- **Ventana de silencio 8:00-21:00 hora de Guayaquil (§11.9) con una sola regla**: si el horario natural cae fuera, se mueve a las 20:00 de la noche anterior — sin bifurcar "cae de madrugada" vs "cae muy tarde" caso por caso.
- **Costo de WhatsApp con un valor de referencia fijo** (`0.02` USD, categoría utility) mientras no exista integración real con las tarifas de Meta — placeholder documentado en `EnviarNotificacionesProgramadas`, no un número negociado con nadie.

---

## Fase 10 — Billing ✅

Fuera de la v1 como función cobrada (§15.2, §9.7) — pero el código se construyó igual, mismo criterio que Reviews/Notifications con sus integraciones externas apagadas.

- [x] **10.1** Suscripción y cobros — administrativo, sin pasarela (`SuscripcionService`, `CobroService`)
- [x] **10.2** Liquidación de comisiones — **con lock del periodo** (§5.3, `lockForUpdate()` en `LiquidacionService::sumarPeriodo()`), aquí sí es plata
- [x] **10.3** Facturación electrónica SRI — puerto `EmisorComprobanteSri` listo, proveedor real diferido (bloqueador externo)
- [x] **10.4** Tests: 14 casos nuevos — suscripción (4), cobros (3), liquidación (7, incluida la verificación de que `generarBorrador()` toma el lock)

**Decisiones de esta fase, para no repetirlas:**

- **Mismo patrón de puerto que WhatsApp/FCM (Fase 9) para el SRI**: `EmisorComprobanteSri` con `LogEmisorComprobanteSri`/`FakeEmisorComprobanteSri` — sin RUC de facturación, firma electrónica ni proveedor contratado (bloqueador externo). `CobroService::marcarPagado()` ya pide el comprobante a este puerto; conectar el proveedor real es solo cambiar el binding en `BillingServiceProvider`.
- **Sin pasarela de pago, ni falta que hace**: `POST /cobros/{cobro}/marcar-pagado` es un registro administrativo — el cobro real ocurre por fuera (transferencia, efectivo, Payphone) y alguien lo marca a mano. Esto es literal al §10.1: *"la liquidación de comisiones no necesita que la plataforma maneje plata."*
- **`generarBorrador()` se puede regenerar mientras siga en `borrador`, nunca si ya está `cerrada`/`pagada`** — se descubrió que el `updateOrCreate` original sobrescribía el `estado` sin condición (hubiera degradado silenciosamente una liquidación cerrada de vuelta a borrador); se corrigió con una comprobación explícita antes de recalcular, cubierta con test.
- **El lock del periodo (§5.3) es la única excepción documentada a "camino optimista sin locks"** en todo el proyecto: `LiquidacionService::sumarPeriodo()` usa `lockForUpdate()` sobre las citas del rango exacto, para que un `CitaService::agregarProducto()` concurrente (que hace `UPDATE` sobre la misma fila `cita`) tenga que esperar a que la liquidación termine. Verificado con un test que inspecciona el SQL ejecutado (`DB::listen()`), no con un rig de concurrencia real multi-proceso como el de citas (Fase 5) — `lockForUpdate()` es un primitivo estándar de Postgres/Eloquent, no algo que este proyecto inventó y necesite probar desde cero.
- **Gating Free/Pro en el `Resource`, no en la creación** (§9.4-9.5): cualquier plan puede generar/cerrar una liquidación (es "la red de seguridad del producto"), pero `LiquidacionResource` esconde el desglose (`total_servicios`/`total_productos`/`comision_servicios`/`comision_productos`) si el negocio no es Pro — deja ver `total_a_pagar`/`total_propinas`.
- **Sin enforcement de límites de plan** (1 local/1 profesional en Free vs. varios en Pro, §9.4): no está en el checklist de esta fase y contradice el §9.7 ("todo gratis los primeros ~6 meses, Pro incluido") — es una decisión de negocio para cuando se decida "encender" el Pro de verdad, no algo que haya que construir ahora.
- **`cancelar()` no modela periodo de gracia**: sin cobro real que reintentar, no hay nada que "gracia" resolvería en la v1 — cancelar baja el negocio a `free` de inmediato.

---

## Fase 11 — Producción ⬅ **siguiente**

- [ ] **11.1** Octane con FrankenPHP
- [ ] **11.2** Horizon
- [ ] **11.3** Sentry y logs JSON
- [ ] **11.4** Métricas del §12.10
- [ ] **11.5** Despliegue, respaldos, PgBouncer

---

## Bloqueadores externos

No dependen del código y conviene empezarlos ya:

- [ ] **Plantillas de WhatsApp aprobadas por Meta** — confirmación, recordatorio 24 h, recordatorio 2 h, cancelación, cupo liberado y OTP. El §11.9 advierte: *"este trámite bloquea el lanzamiento si se deja al final"*
- [ ] Proyecto Firebase (FCM)
- [ ] APNs Auth Key de Apple
- [ ] API key de Google Maps
- [ ] Cuenta de Sentry

---

## Decisiones abiertas (§16)

Ninguna es técnica; todas cambian el producto.

- [ ] **§16.4 — ¿comisión sobre precio lleno o con descuento?** Afecta la Fase 6. La especificación insiste en decidirlo ahora: *"cambiarlo después es cambiarle la plata a la gente"*
- [ ] **§16.1 — ¿perfil del profesional público y buscable?** Afecta las Fases 7 y 8
- [ ] §16.2 — ¿reseña del profesional además del local?
- [ ] §16.3 — ¿precio por profesional en la v1?
- [ ] §16.6 — ¿cuándo se activa grooming? Si se activa, revisar si `vertical` merece tabla propia (§4.5)

Y el §17: **las 10 entrevistas con barberías.** El documento es explícito en que eso decide si vale la pena construirlo y en qué orden.
