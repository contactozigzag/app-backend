#!/bin/bash
# Enable PostGIS in template1 so every new database (including test DBs) inherits the extension.
set -e
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname template1 <<-EOSQL
    CREATE EXTENSION IF NOT EXISTS postgis;
EOSQL
