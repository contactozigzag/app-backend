<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Real-time tracking improvements:
 *
 * 1. Unique indexes on location_updates to enforce GPS idempotency at the DB level.
 * 2. Composite index on (active_route_id, timestamp DESC) for "latest location for route".
 * 3. PostGIS extension + generated geography(Point,4326) columns (requires postgis/postgis
 *    image or a PostgreSQL installation that includes the PostGIS extension binaries).
 *    The PostGIS steps are wrapped in a PL/pgSQL DO block and will emit a NOTICE (not fail)
 *    when PostGIS is not installed — useful for local dev with plain postgres:18-alpine.
 *    For production use the postgis/postgis:18-3.5-alpine image to get all benefits.
 */
final class Version20260406203840 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add GPS idempotency indexes, route location index, and PostGIS geography columns (optional)';
    }

    public function up(Schema $schema): void
    {
        // 1. Unique indexes for GPS idempotency (driver + route + device timestamp)
        //    active_route_id is nullable → two partial indexes to cover both cases.
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uniq_location_driver_route_ts
            ON location_updates (driver_id, active_route_id, timestamp)
            WHERE active_route_id IS NOT NULL
        SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uniq_location_driver_ts_no_route
            ON location_updates (driver_id, timestamp)
            WHERE active_route_id IS NULL
        SQL);

        // 2. Composite descending index for efficient "latest for route" queries
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_location_route_ts_desc
            ON location_updates (active_route_id, timestamp DESC)
        SQL);

        // 3. PostGIS — wrapped in a DO block so it is non-fatal when PostGIS
        //    binaries are not present (e.g. plain postgres:18-alpine dev images).
        //    In production use postgis/postgis:18-3.5-alpine to enable these columns.
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                -- Enable extension (idempotent)
                CREATE EXTENSION IF NOT EXISTS postgis;

                -- Generated geography column on location_updates
                -- (longitude = X axis, latitude = Y axis in ST_MakePoint)
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'location_updates' AND column_name = 'point'
                ) THEN
                    ALTER TABLE location_updates
                    ADD COLUMN point geography(Point, 4326)
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN longitude IS NOT NULL AND latitude IS NOT NULL
                            THEN ST_SetSRID(
                                ST_MakePoint(longitude::float8, latitude::float8), 4326
                            )::geography
                            ELSE NULL
                        END
                    ) STORED;

                    CREATE INDEX idx_location_updates_point
                        ON location_updates USING GIST (point);
                END IF;

                -- Generated geography column on address
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'address' AND column_name = 'point'
                ) THEN
                    ALTER TABLE address
                    ADD COLUMN point geography(Point, 4326)
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN longitude IS NOT NULL AND latitude IS NOT NULL
                            THEN ST_SetSRID(
                                ST_MakePoint(longitude::float8, latitude::float8), 4326
                            )::geography
                            ELSE NULL
                        END
                    ) STORED;

                    CREATE INDEX idx_address_point
                        ON address USING GIST (point);
                END IF;

            EXCEPTION WHEN OTHERS THEN
                RAISE NOTICE 'PostGIS not available — skipping geography columns: %', SQLERRM;
            END;
            $$
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Remove PostGIS columns and indexes if they exist
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                DROP INDEX IF EXISTS idx_addresses_point;
                DROP INDEX IF EXISTS idx_location_updates_point;
                ALTER TABLE addresses DROP COLUMN IF EXISTS point;
                ALTER TABLE location_updates DROP COLUMN IF EXISTS point;
            EXCEPTION WHEN OTHERS THEN
                RAISE NOTICE 'PostGIS cleanup skipped: %', SQLERRM;
            END;
            $$
        SQL);

        $this->addSql('DROP INDEX IF EXISTS idx_location_route_ts_desc');
        $this->addSql('DROP INDEX IF EXISTS uniq_location_driver_ts_no_route');
        $this->addSql('DROP INDEX IF EXISTS uniq_location_driver_route_ts');
    }
}
