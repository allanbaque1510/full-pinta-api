-- Inicialización de FullPinta.
-- Se ejecuta una sola vez, al crear el volumen del contenedor.
--
-- Las extensiones van a un esquema propio (`extensions`), NO a `public`:
-- `migrate:fresh` borra todo lo que hay en `public`, y ahí PostGIS deja
-- `spatial_ref_sys`. Con las extensiones fuera, rehacer la base en tests
-- no rompe PostGIS.

CREATE DATABASE fullpinta_test OWNER fullpinta;

\connect fullpinta

CREATE SCHEMA IF NOT EXISTS extensions AUTHORIZATION fullpinta;
CREATE EXTENSION IF NOT EXISTS postgis     WITH SCHEMA extensions;
CREATE EXTENSION IF NOT EXISTS btree_gist  WITH SCHEMA extensions;

-- Postgres no trae un tipo de rango para `time`; hace falta para detectar
-- traslape de turnos (§4.1 de la especificación).
DO $do$
BEGIN
    CREATE TYPE extensions.franja AS RANGE (subtype = time);
EXCEPTION
    WHEN duplicate_object THEN NULL;
END
$do$;

ALTER DATABASE fullpinta SET search_path TO public, extensions;

\connect fullpinta_test

CREATE SCHEMA IF NOT EXISTS extensions AUTHORIZATION fullpinta;
CREATE EXTENSION IF NOT EXISTS postgis     WITH SCHEMA extensions;
CREATE EXTENSION IF NOT EXISTS btree_gist  WITH SCHEMA extensions;

DO $do$
BEGIN
    CREATE TYPE extensions.franja AS RANGE (subtype = time);
EXCEPTION
    WHEN duplicate_object THEN NULL;
END
$do$;

ALTER DATABASE fullpinta_test SET search_path TO public, extensions;
