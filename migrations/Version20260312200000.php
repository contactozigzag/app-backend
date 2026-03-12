<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Backfill vehicle.driver_id from vehicle.user_id via the driver table.
 *
 * For every vehicle that already has a user_id but no driver_id, we look up
 * the driver whose user_id matches and set vehicle.driver_id accordingly.
 * Vehicles with no matching driver row are left with driver_id = NULL so they
 * can be handled manually before user_id is eventually dropped.
 */
final class Version20260312200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill vehicle.driver_id from vehicle.user_id through the driver table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE vehicle v
            INNER JOIN driver d ON d.user_id = v.user_id
            SET v.driver_id = d.id
            WHERE v.driver_id IS NULL
              AND v.user_id IS NOT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE vehicle v
            INNER JOIN driver d ON d.id = v.driver_id
            SET v.driver_id = NULL
            WHERE v.user_id = d.user_id
            SQL);
    }
}
