<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add driver_id FK to payment table (Marketplace: one parent → one driver)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment ADD driver_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_payment_driver FOREIGN KEY (driver_id) REFERENCES driver (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_payments_driver ON payment (driver_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_payment_driver');
        $this->addSql('DROP INDEX idx_payments_driver ON payment');
        $this->addSql('ALTER TABLE payment DROP driver_id');
    }
}
