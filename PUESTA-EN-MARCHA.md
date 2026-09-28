# Puesta en marcha

Guía operativa para levantar FullPinta API. Para el *qué* y el *por qué* del producto, ver [`context/fullpinta-especificacion.md`](context/fullpinta-especificacion.md). Este documento es solo el *cómo* del entorno.

---

## Local (desarrollo)

### Requisitos

- PHP 8.3+ con extensiones `pdo_pgsql` y `pgsql` habilitadas
- Composer
- Docker (para PostgreSQL + PostGIS y Redis)
- Node/npm si vas a tocar algo de frontend de assets (el proyecto es API pura, normalmente no hace falta)

### Pasos

```bash
git clone <repo> full-pinta-api && cd full-pinta-api

composer install
cp .env.example .env
php artisan key:generate

docker compose up -d           # PostgreSQL + PostGIS y Redis
php artisan migrate
```

También existe `composer run setup`, que encadena `composer install` + copiar `.env` + `key:generate` + `migrate --force` en un solo paso (útil para reconstruir el entorno desde cero).

### Datos de prueba

`php artisan db:seed` (el `DatabaseSeeder` por defecto) solo carga catálogo maestro (rubros, categorías, catálogo de servicios, planes...) — datos que la plataforma define y que también se cargan en producción. No crea negocios, locales, profesionales ni citas.

Para un escenario completo con datos ficticios (3 negocios — barbería, estética, uñas —, cada uno con local, servicios, profesionales con turno y habilidades, clientes, e historial de citas en distintos estados), correr:

```bash
php artisan db:seed --class=DemoDataSeeder
```

Al terminar imprime una tabla con las credenciales de cada cuenta creada (propietario, profesional, recepción, cliente), todas con la contraseña `Fullpinta123!`. Login: `POST /api/v1/auth/login` con `email`+`password`.

No es idempotente (correrlo dos veces duplica el escenario) y no corre en `APP_ENV=production` — es solo para desarrollo local y demos.

### Servicios en Docker

Corren en puertos desplazados para no chocar con una instalación existente de Laragon/XAMPP:

| Servicio | Puerto host | Credenciales |
|---|---|---|
| PostgreSQL 17 + PostGIS 3.5 | `5433` | `fullpinta` / `fullpinta`, base `fullpinta` |
| Redis 7 | `6380` | sin clave |

```bash
docker compose ps                                                   # estado
docker exec -it fullpinta-postgres psql -U fullpinta -d fullpinta   # consola SQL
docker compose down                                                 # detener (los datos persisten en el volumen)
```

Docker **solo** levanta base de datos y caché. La aplicación PHP en sí no corre en un contenedor — corre nativa, contra esos dos servicios expuestos en `127.0.0.1`.

### Extensiones de PHP

Se requieren `pdo_pgsql` y `pgsql` habilitadas en el `php.ini` que use tu CLI/servidor web. Verifica con:

```bash
php -m | grep pgsql
```

Si no devuelve nada, edita el `php.ini` activo (`php --ini` te dice cuál es) y descomenta:

```ini
extension=pdo_pgsql
extension=pgsql
```

**Importante:** si el proceso de tu servidor web (Apache/Nginx) ya estaba corriendo, no basta con editar el `php.ini` — hay que **reiniciar el servidor web** para que recargue los módulos. Ese reinicio es fácil de olvidar y produce el error engañoso `could not find driver` incluso con la extensión ya habilitada en el archivo.

Redis se usa vía **predis** (cliente en PHP puro), así que no hace falta compilar `phpredis`.

### Cómo servir el proyecto

Dos formas, ambas válidas para desarrollo:

**1. Servidor embebido de Artisan** (más simple, no depende de configuración de Apache/Nginx):

```bash
composer run dev   # php artisan serve + queue:listen en paralelo
```

Accede en `http://127.0.0.1:8000`.

**2. Apache de Laragon** (si ya tienes Laragon corriendo y prefieres no levantar un proceso aparte):

El proyecto vive fuera de `C:\laragon\www` (p. ej. en `E:\www\full-pinta-api`), pero como esa carpeta está en `DocumentRootList` de Laragon, el vhost por defecto ya la sirve. Como Laravel necesita que la raíz pública sea la carpeta `public/` (no la raíz del proyecto), accede con `/public/` en la ruta:

```
http://localhost/full-pinta-api/public/api/v1
```

**Trampa conocida:** no intentes "limpiar" esa URL con un `Alias` de Apache que mapee `/full-pinta-api` → `.../full-pinta-api/public` sin `/public/` visible. El `.htaccess` de Laravel asume que `public/` es la raíz real del sitio (`DocumentRoot`), no un `Alias`; bajo un `Alias`, el `RewriteRule` entra en bucle y Apache falla con `AH00124: Request exceeded the limit of 10 internal redirects`, mostrando un 500 genérico de Apache (no el de Laravel).

Si en algún momento se quiere una URL sin `/public/`, la forma correcta es un **vhost real** con `DocumentRoot` apuntando directo a `.../full-pinta-api/public` y un dominio propio (p. ej. `full-pinta-api.test`, vía el menú **www** de Laragon, que crea el vhost y la entrada en `hosts` automáticamente). Un `Alias` no es equivalente a un `DocumentRoot` para este caso.

### Comandos del día a día

```bash
# Tests y formato
php artisan test                    # suite completa
php artisan test --filter=Nombre    # un test
vendor/bin/pint --dirty             # formatea solo lo modificado
vendor/bin/pint                     # formatea todo el proyecto

# Base de datos
php artisan migrate                 # aplicar migraciones nuevas
php artisan migrate:fresh --seed    # rehacer la base local desde cero + catálogo maestro
php artisan db:seed                 # solo el catálogo maestro (sin rehacer tablas)
php artisan db:seed --class=DemoDataSeeder   # + escenario ficticio completo (ver arriba)

# Colas
php artisan queue:work --queue=critica,notificaciones,proyecciones,batch
php artisan queue:listen             # igual, pero recarga código en cada job (solo dev)

# Servidor
composer run dev                     # serve + queue:listen en paralelo

# Inspección
php artisan route:list --except-vendor
php artisan config:show fullpinta    # config propia de la app (config/fullpinta.php)
php artisan tinker                   # REPL con el framework cargado
```

### Autocompletado del IDE (`barryvdh/laravel-ide-helper`)

Dependencia de desarrollo (no viaja a producción — `composer install --no-dev` no la instala). Genera metadata estática para que el editor entienda los métodos mágicos de Eloquent/Facades, que de otro modo el analizador no puede inferir (por ejemplo, marca en rojo `$modelo->columna_real` o `$this->app->isProduction()` dentro de un `ServiceProvider` aunque el código sea correcto en tiempo de ejecución).

```bash
php artisan ide-helper:generate            # autocompletado de Facades -> _ide_helper.php
php artisan ide-helper:models --nowrite    # docblocks de columnas/relaciones de cada modelo, en archivo aparte -> _ide_helper_models.php
php artisan ide-helper:meta                # metadata para PhpStorm -> .phpstorm.meta.php
```

Los tres archivos generados están en `.gitignore` (se regeneran localmente, no se commitean) y **hay que volver a correrlos después de migraciones o cambios de esquema** — si no, el autocompletado queda desactualizado contra la base real. `ide-helper:models` acepta `-W` para escribir los docblocks directo en cada modelo en vez de un archivo aparte; este proyecto usa `--nowrite` a propósito, para no ensuciar el diff de cada modelo con comentarios generados.

---

## Producción

**Estado actual: no implementada.** Es la Fase 11 del [plan de implementación](context/plan-implementacion.md), todavía pendiente. Lo que sigue es la intención documentada, no un procedimiento probado — no lo tomes como un runbook cerrado.

### Plataforma objetivo

[Laravel Cloud](https://cloud.laravel.com/), según lo establecido en [`CLAUDE.md`](CLAUDE.md). Cuando se llegue a esa fase, usar la skill `deploying-to-cloud` para el despliegue y la gestión de entornos, bases de datos y colas.

### Pendiente en la Fase 11 (no construido todavía)

- **11.1** Octane con FrankenPHP
- **11.2** Horizon (hoy las colas se corren con `queue:work`/`queue:listen` manual)
- **11.3** Sentry y logs en formato JSON
- **11.4** Métricas (§12.10 de la especificación)
- **11.5** Despliegue, respaldos, PgBouncer

### Requisitos de infraestructura ya conocidos (aunque el despliegue no esté armado)

- **PostgreSQL con PostGIS y `btree_gist`** — no es negociable, ver README. Cualquier proveedor gestionado debe soportar ambas extensiones.
- **Redis** para caché, sesiones y colas.
- **Cron estándar de Laravel** (`* * * * * php artisan schedule:run`) — el scheduler ya existe en `bootstrap/app.php`, pero no hay nada ejecutándolo fuera del entorno de desarrollo.
- **Variables de entorno de producción** mínimas: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` como secreto gestionado (nunca committeado), `DB_*`/`REDIS_*` apuntando a servicios gestionados (no a los contenedores de `docker-compose.yml`, que son solo para desarrollo local).
- **Bloqueadores externos** que no dependen del código y conviene resolver desde ya: plantillas de WhatsApp aprobadas por Meta, proyecto Firebase (FCM), APNs Auth Key, API key de Google Maps, cuenta de Sentry. Ver detalle en `context/plan-implementacion.md`.

Cuando se implemente la Fase 11, este documento debe actualizarse con el procedimiento real (no antes) — evitar documentar pasos que todavía no se han ejecutado ni verificado.
