-- Runs only on a NEW PostgreSQL volume. Never alters existing users or data.
\getenv app_password DB_PASSWORD
CREATE ROLE alquivo LOGIN PASSWORD :'app_password' NOSUPERUSER NOCREATEDB NOCREATEROLE;
CREATE DATABASE alquivo OWNER alquivo;
\connect alquivo
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT ALL ON SCHEMA public TO alquivo;
