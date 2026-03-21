<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add prefix indexes to support the driver search Doctrine fallback (LIKE 'value%' queries).
 */
final class Version20260320000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add prefix indexes on driver.nickname, user.first_name, user.last_name, user.identification_number for driver search fallback';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_driver_nickname ON driver (nickname(20))');
        $this->addSql('CREATE INDEX idx_user_first_name ON user (first_name(20))');
        $this->addSql('CREATE INDEX idx_user_last_name ON user (last_name(20))');
        $this->addSql('CREATE INDEX idx_user_identification ON user (identification_number(10))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_driver_nickname ON driver');
        $this->addSql('DROP INDEX idx_user_first_name ON user');
        $this->addSql('DROP INDEX idx_user_last_name ON user');
        $this->addSql('DROP INDEX idx_user_identification ON user');
    }
}
