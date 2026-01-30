<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130005714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE vehicle (id INT AUTO_INCREMENT NOT NULL, license_plate VARCHAR(50) NOT NULL, make VARCHAR(100) NOT NULL, model VARCHAR(100) NOT NULL, capacity INT NOT NULL, year INT DEFAULT NULL, color VARCHAR(50) DEFAULT NULL, type VARCHAR(50) DEFAULT NULL, driver_id INT DEFAULT NULL, INDEX IDX_1B80E486C3423909 (driver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE vehicle ADD CONSTRAINT FK_1B80E486C3423909 FOREIGN KEY (driver_id) REFERENCES driver (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE vehicle DROP FOREIGN KEY FK_1B80E486C3423909');
        $this->addSql('DROP TABLE vehicle');
    }
}
