<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Mercado Pago OAuth fields to driver table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE driver
             ADD mp_access_token    LONGTEXT         DEFAULT NULL,
             ADD mp_refresh_token   LONGTEXT         DEFAULT NULL,
             ADD mp_account_id      VARCHAR(100)     DEFAULT NULL,
             ADD mp_token_expires_at DATETIME        DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\''
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE driver
             DROP mp_access_token,
             DROP mp_refresh_token,
             DROP mp_account_id,
             DROP mp_token_expires_at'
        );
    }
}
