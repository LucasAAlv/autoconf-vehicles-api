#!/bin/sh
set -e

# Runs once, automatically, only against a brand-new (empty) Postgres data
# directory — the official postgres image executes every script in
# /docker-entrypoint-initdb.d on first boot, never again after that. Creates
# a second database on the same server so the test suite (autoconf_vehicles_test)
# never shares tables with the development database (autoconf_vehicles).
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE "${POSTGRES_DB}_test" OWNER "$POSTGRES_USER";
EOSQL
