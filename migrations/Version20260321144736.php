<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260321144736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add driver rate pricing model: DriverRate table, Driver.pricingModel, Payment.rateSnapshot, Subscription driver/route FKs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE driver_rate (id INT AUTO_INCREMENT NOT NULL, pricing_model VARCHAR(30) NOT NULL, amount NUMERIC(10, 2) DEFAULT NULL, per_student_amount NUMERIC(10, 2) DEFAULT NULL, currency VARCHAR(3) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, driver_id INT NOT NULL, route_id INT DEFAULT NULL, INDEX IDX_3846D9ACC3423909 (driver_id), INDEX IDX_3846D9AC34ECB4E6 (route_id), UNIQUE INDEX uniq_driver_rate_driver_route (driver_id, route_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE driver_rate ADD CONSTRAINT FK_3846D9ACC3423909 FOREIGN KEY (driver_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE driver_rate ADD CONSTRAINT FK_3846D9AC34ECB4E6 FOREIGN KEY (route_id) REFERENCES routes (id)');
        $this->addSql('ALTER TABLE driver ADD pricing_model VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD rate_snapshot JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE subscription ADD driver_id INT DEFAULT NULL, ADD route_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3C3423909 FOREIGN KEY (driver_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D334ECB4E6 FOREIGN KEY (route_id) REFERENCES routes (id)');
        $this->addSql('CREATE INDEX IDX_A3C664D3C3423909 ON subscription (driver_id)');
        $this->addSql('CREATE INDEX IDX_A3C664D334ECB4E6 ON subscription (route_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE driver_rate DROP FOREIGN KEY FK_3846D9ACC3423909');
        $this->addSql('ALTER TABLE driver_rate DROP FOREIGN KEY FK_3846D9AC34ECB4E6');
        $this->addSql('DROP TABLE driver_rate');
        $this->addSql('ALTER TABLE driver DROP pricing_model');
        $this->addSql('ALTER TABLE payment DROP rate_snapshot');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D3C3423909');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D334ECB4E6');
        $this->addSql('DROP INDEX IDX_A3C664D3C3423909 ON subscription');
        $this->addSql('DROP INDEX IDX_A3C664D334ECB4E6 ON subscription');
        $this->addSql('ALTER TABLE subscription DROP driver_id, DROP route_id');
    }
}
