---
name: migracion
description: Escribir migraciones y esquema de FullPinta según las convenciones del §4 de la especificación (uuid, varchar+CHECK, timestamptz UTC, rangos '[)', constraints EXCLUDE de Postgres). Úsala al crear o alterar cualquier tabla, índice o constraint.
---

# Migraciones de FullPinta

Fuente de verdad: `context/fullpinta-especificacion.md` §4. Ante cualquier duda, ese documento manda sobre esta skill.

## Dónde viven y en qué orden corren

**Todas en `database/migrations`**, no repartidas por módulo. El esquema no es modular aunque el código lo sea: `cita` tiene claves foráneas a `local`, `usuario`, `profesional`, `recurso`, `servicio_local`, `producto` y `mascota`. Es una sola base y una sola línea de tiempo.

Laravel ordena **por nombre de archivo**, globalmente. El prefijo es lo que garantiza que `usuario` exista antes de que `negocio` le haga FK:

```
000100  Identity        usuario, cliente_perfil, mascota, consentimiento
000200  Directory       negocio, local, horario_local, amenidad, ...
000300  Catalog         servicio_categoria, catalogo_servicio, ...
000400  Staffing        profesional, asignacion, turno, recurso, ...
000450  favorito        (necesita local Y profesional)
000500  Scheduling      cita, cita_item, cita_evento, ...
000600  Reviews
000700  Notifications
000800  Billing
```

Los huecos son intencionales: permiten insertar en medio sin renumerar.

Para un cambio nuevo, `make:migration` normal — lleva la fecha real y se ordena después del esquema base, que es lo correcto:

```bash
php artisan make:migration agregar_campo_x_a_cita
```

Ojo con `migrate:rollback`: deshace el **lote completo**, no una migración. El esquema base entero corrió como lote 1.

## Convenciones no negociables

| Aspecto | Regla |
|---|---|
| PK | `uuid`, **generado en la aplicación**, nunca `bigIncrements`. En el modelo: `HasUuids`. **Sin excepción** — incluso donde hay una clave natural obvia (un nombre asignado externamente, una clave de idempotencia, una FK 1:1). La clave natural queda como columna `UNIQUE` normal, no como PK. Decidido explícitamente el 2026-09-15 tras dudarlo con `cliente_perfil`/`idempotencia`/`plantilla_whatsapp` — no reabrir la discusión. |
| Nombre de tabla | Singular, snake_case: `cita`, `servicio_local`. Sin plural. |
| Enums | `varchar` + `CHECK`. **Nunca** `CREATE TYPE ... AS ENUM` ni `$table->enum()` (Laravel lo mapea a check pero pierde el nombre). |
| Timestamps | `timestamptz` siempre. Todo se guarda en **UTC**; la conversión a `America/Guayaquil` es de presentación. |
| Rangos | Siempre `'[)'`. Una cita que termina 10:30 y otra que empieza 10:30 **no** se traslapan. |
| Borrado | Soft delete por campo `estado` o `activo`. No se borran locales, profesionales ni citas. |
| Dinero | `numeric(10,2)`. Nunca `float` ni `double`. |
| Precios y comisiones | **Congelados** en la fila de la transacción, no leídos por join al momento de consultar. |

## Borrado: `activo` vs `estado` — no son intercambiables

Son dos mecanismos distintos, y confundirlos rompe la semántica de la tabla.

**Campo `activo` (booleano) → borrar es `activo = false`.** Sin matices, sin excepciones. Tablas: `mascota`, `amenidad`, `servicio_categoria`, `catalogo_servicio`, `servicio_local`, `producto`, `recurso`, `device_token`.

```php
$servicioLocal->update(['activo' => false]);   // ✓
$servicioLocal->delete();                       // ✗ nunca
```

**Campo `estado` (varchar + CHECK, varios valores) → borrar es una transición, no un valor fijo.** Uno de los valores del enum puede incluso llamarse `activo` (es el caso de `local`: `borrador, activo, pausado, suspendido`) — eso no lo convierte en booleano. La transición correcta depende de **quién** desactiva y **por qué**:

```php
// El dueño pausa su local: puede reactivarlo cuando quiera.
$local->update(['estado' => 'pausado']);

// La plataforma lo suspende (moderación, TOS): no se reactiva solo.
$local->update(['estado' => 'suspendido']);
```

Tratar `estado='activo'` como si fuera un booleano pierde esa distinción — y sí importa: un local pausado y uno suspendido no deberían poder reactivarse por el mismo camino. Mismo patrón en `cita` (§6), `suscripcion`, `cobro`, `resena`, `reporte`, `notificacion`, `espera`, `plantilla_whatsapp`, `solicitud_catalogo`, `liquidacion`: cada una con su propia máquina de estados donde "borrado" es un estado más entre varios, nunca un interruptor.

## Un valor repetido en varias filas o varias tablas es tabla de parámetros, no `varchar` suelto

Si un dato se repite como texto en más de una fila (una categoría, un tipo, una vertical) y encima tiene atributos propios que describirlo bien exige (`nombre`, `icono`, `orden`), **es una tabla `id, codigo, nombre, activo`, referenciada por `id`** — no un `varchar` + `CHECK` repetido en cada tabla que lo necesita. Ejemplos ya en el esquema: `servicio_categoria`, `amenidad_categoria`, `vertical`.

Señal inequívoca de que ya cruzó la línea: el mismo `varchar` + `CHECK` con la misma lista de valores aparece en **más de una tabla**. Pasó con `vertical` (repetido en `servicio_categoria`, `catalogo_servicio` y `solicitud_catalogo` antes del 2026-09-15) y con `amenidad.categoria`. La corrección: tabla de parámetros con `id` uuid, y cada tabla que antes tenía el `varchar` pasa a tener `xxx_id` con FK.

Efecto colateral importante al normalizar: si una tabla tenía el dato duplicado "para garantizar consistencia" (p. ej. `catalogo_servicio.vertical` + `categoria_codigo`, con FK compuesta para que ambos coincidieran), **no dupliques la columna con la nueva FK simple** — derívala de la relación (`categoria_id -> servicio_categoria.vertical_id`) y accede vía relación de Eloquent (`$this->categoria->vertical`), con el `->with(...)` correspondiente para no romper `preventLazyLoading()`. Guardar la misma cosa en dos columnas es justamente el problema que se está corrigiendo.

Un enum que **no** se repite entre tablas y no tiene atributos propios (p. ej. `tipo_recurso`, `mascota_tamano`) se queda como `varchar` + `CHECK` normal — no todo enum merece tabla.

## Ordenar "por más reciente" nunca por `created_at`, siempre por `id`

`timestampsTz()` (y `timestampTz()` sueltos) guardan por defecto **segundos enteros, sin fracción** (`timestamp(0)`). Dos filas creadas en la misma petición o en peticiones seguidas caen fácil en el mismo segundo, y ahí `ORDER BY created_at DESC` no tiene con qué desempatar — Postgres puede devolver cualquiera de las dos primero, y ese orden no está garantizado que coincida con cuál se insertó realmente antes.

Costó una tarde de depuración real: `VerificarOtp` cerraba "la sesión más antigua" por encima del límite de dispositivos con `orderByDesc('created_at')`, y en tests con logins seguidos (mismo segundo) a veces cerraba el token equivocado — parecía una fuga de autenticación entre tests, y no lo era.

**Regla:** para "el más reciente"/"el más antiguo" con precisión que importe, ordenar por el `id` autoincremental (o, en tablas con PK uuid, por una columna secuencial dedicada) — nunca por una columna `timestamp` sola, sin importar cuán junitos parezcan los eventos en desarrollo.

## Un `DEFAULT` de Postgres no lo ve el modelo recién creado

Trampa real, ya mordió varias veces en la Fase 3: una columna con `->default(...)` en la migración resuelve el valor **en la base**, no en PHP. Si el `Application/{Modelo}Service::crear()` omite esa columna (porque el campo era opcional en el `FormRequest` y no vino en el body), Eloquent tampoco la incluye en el `INSERT` — Postgres aplica su default, pero el objeto que `create()` devuelve **nunca vuelve a leer la fila**, así que ese atributo queda en `null` en memoria. La API responde `null` donde debería responder `false`/`0`/el default real, y nadie lo nota hasta que un test lo compara explícito.

```php
// ✗ Si `precio_desde` no vino en $datos, el ServicioLocal recién creado
// devuelve `precio_desde: null` en la respuesta, aunque la fila en Postgres
// tenga `false` guardado.
$local->servicios()->create($datos);

// ✓ El default se repite en PHP — a propósito, como documentación
// ejecutable de cuál es el default real de la columna — y $datos lo
// sobrescribe si el cliente sí mandó un valor.
$local->servicios()->create([
    'precio_desde' => false,
    'buffer_min' => 0,
    'comisionable' => true,
    ...$datos,
    'activo' => true, // este no lo manda nunca el cliente: siempre forzado
]);
```

**Regla:** en todo `crear()`/`agregar()` de un `{Modelo}Service`, cualquier columna con `->default(...)` en la migración que el `FormRequest` correspondiente marque `sometimes` (opcional) se repite explícita en el array de creación, con el mismo valor que la migración. Verificar con un test que compare el campo tras un `POST` sin mandarlo — `assertJsonPath('buffer_min', 0)`, no solo que el recurso se creó.

## Lo que el Schema builder no puede hacer

Constraints `EXCLUDE`, columnas generadas y el tipo `franja` van en `DB::statement()` dentro de la misma migración.

```php
public function up(): void
{
    Schema::create('turno', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('asignacion_id')->constrained('asignacion');
        $table->foreignUuid('profesional_id')->constrained('profesional'); // desnormalizado a propósito
        $table->foreignUuid('local_id')->constrained('local');             // idem
        $table->smallInteger('dia_semana');
        $table->time('entra');
        $table->time('sale');
        $table->date('vigente_desde');
        $table->date('vigente_hasta')->nullable();
        $table->timestampsTz();
    });

    DB::statement("ALTER TABLE turno ADD CONSTRAINT turno_dia_semana_check CHECK (dia_semana BETWEEN 0 AND 6)");
    DB::statement("ALTER TABLE turno ADD CONSTRAINT turno_sale_check CHECK (sale > entra)");

    DB::statement("ALTER TABLE turno ADD COLUMN rango franja
        GENERATED ALWAYS AS (franja(entra, sale, '[)')) STORED");
    DB::statement("ALTER TABLE turno ADD COLUMN vigencia daterange
        GENERATED ALWAYS AS (daterange(vigente_desde, vigente_hasta, '[)')) STORED");

    DB::statement("ALTER TABLE turno ADD CONSTRAINT turno_sin_traslape
        EXCLUDE USING gist (
            profesional_id WITH =,
            dia_semana     WITH =,
            rango          WITH &&,
            vigencia       WITH &&
        )");
}
```

`profesional_id` y `local_id` en `turno` están desnormalizados **solo** para habilitar ese constraint. Se escriben en la misma transacción que la asignación.

## Los tres constraints críticos (§4.11)

Sin ellos la lógica de aplicación falla bajo concurrencia. Si tocas `turno` o `cita`, verifica que sigan puestos.

1. **`turno_sin_traslape`** — un profesional no puede tener dos turnos encimados, aunque sean de locales distintos. `vigencia` es indispensable: sin ella, un cambio de horario a futuro choca con el actual y Postgres lo rechaza aunque sea legítimo.

2. **`cita_profesional_sin_traslape`** — sobre `profesional_id` **solo**, nunca sobre `(local_id, profesional_id)`:
```sql
ALTER TABLE cita ADD CONSTRAINT cita_profesional_sin_traslape
  EXCLUDE USING gist (profesional_id WITH =, rango WITH &&)
  WHERE (estado IN ('reservada','confirmada','en_curso'));
```
Un profesional es un solo cuerpo y no puede estar en dos locales a la vez. Scopearlo por local —que es el instinto natural— introduce exactamente ese bug.

3. **`cita_recurso_sin_traslape`** — el recurso ya pertenece a un local, así que es implícitamente por local:
```sql
ALTER TABLE cita ADD CONSTRAINT cita_recurso_sin_traslape
  EXCLUDE USING gist (recurso_id WITH =, rango WITH &&)
  WHERE (estado IN ('reservada','confirmada','en_curso') AND recurso_id IS NOT NULL);
```

## Recursos: una fila por unidad física

`recurso` lleva **una fila por silla o mesa real**, nunca una fila con `cantidad = 3`. Con un campo cantidad el constraint de exclusión subvende (permite una sola a la vez) o, si se relaja, sobrevende. Además permite marcar una mesa fuera de servicio sin afectar las otras.

## Índices a mantener (§4.12)

```sql
CREATE INDEX local_ubicacion_gist    ON local USING gist (ubicacion);
CREATE INDEX local_estado_score      ON local (estado, score_ranking);
CREATE INDEX cita_local_inicio       ON cita (local_id, inicio);
CREATE INDEX cita_profesional_inicio ON cita (profesional_id, inicio);
CREATE INDEX cita_cliente_inicio     ON cita (cliente_id, inicio DESC);
CREATE INDEX cita_holds_vencidos     ON cita (expira_at) WHERE estado = 'reservada';
CREATE INDEX turno_local_dia         ON turno (local_id, dia_semana);
CREATE INDEX excepcion_prof_rango    ON excepcion (profesional_id, fecha_inicio, fecha_fin);
CREATE INDEX habilidad_servicio      ON habilidad (servicio_local_id);
CREATE INDEX servicio_local_activo   ON servicio_local (local_id, activo);
```

## No hacer

- **No particionar `cita`.** Postgres exige que la PK incluya la columna de partición (`PRIMARY KEY (id, inicio)`), lo que arrastra claves compuestas a `cita_item`, `cita_producto` y `resena`, y Eloquent las maneja mal. Se revisa a los >5M de filas, no antes (§4.13).
- **No usar enums nativos de Postgres.** Alterarlos es doloroso.
- **No usar `'[]'` en rangos.** Se pierde un slot en cada frontera.
- **No convertir a hora local en la base.** UTC en base, `America/Guayaquil` en presentación.

## Verificar después de migrar

```bash
docker exec fullpinta-postgres psql -U fullpinta -d fullpinta -c "\d+ cita"
docker exec fullpinta-postgres psql -U fullpinta -d fullpinta -c \
  "SELECT conname, contype FROM pg_constraint WHERE conrelid = 'cita'::regclass ORDER BY 1;"
```
