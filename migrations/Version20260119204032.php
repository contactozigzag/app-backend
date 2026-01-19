<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260119204032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE route_stops (id INT AUTO_INCREMENT NOT NULL, stop_order INT NOT NULL, estimated_arrival_time INT DEFAULT NULL, geofence_radius INT NOT NULL, notes LONGTEXT DEFAULT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, route_id INT NOT NULL, student_id INT NOT NULL, address_id INT NOT NULL, INDEX IDX_A4CABD0A34ECB4E6 (route_id), INDEX IDX_A4CABD0ACB944F1A (student_id), INDEX IDX_A4CABD0AF5B7AF75 (address_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE routes (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, start_latitude NUMERIC(10, 6) NOT NULL, start_longitude NUMERIC(10, 6) NOT NULL, end_latitude NUMERIC(10, 6) NOT NULL, end_longitude NUMERIC(10, 6) NOT NULL, estimated_duration INT DEFAULT NULL, estimated_distance INT DEFAULT NULL, polyline LONGTEXT DEFAULT NULL, is_active TINYINT NOT NULL, is_template TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, school_id INT NOT NULL, driver_id INT DEFAULT NULL, INDEX IDX_32D5C2B3C32A47EE (school_id), INDEX IDX_32D5C2B3C3423909 (driver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE route_stops ADD CONSTRAINT FK_A4CABD0A34ECB4E6 FOREIGN KEY (route_id) REFERENCES routes (id)');
        $this->addSql('ALTER TABLE route_stops ADD CONSTRAINT FK_A4CABD0ACB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE route_stops ADD CONSTRAINT FK_A4CABD0AF5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE routes ADD CONSTRAINT FK_32D5C2B3C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE routes ADD CONSTRAINT FK_32D5C2B3C3423909 FOREIGN KEY (driver_id) REFERENCES driver (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE route_stops DROP FOREIGN KEY FK_A4CABD0A34ECB4E6');
        $this->addSql('ALTER TABLE route_stops DROP FOREIGN KEY FK_A4CABD0ACB944F1A');
        $this->addSql('ALTER TABLE route_stops DROP FOREIGN KEY FK_A4CABD0AF5B7AF75');
        $this->addSql('ALTER TABLE routes DROP FOREIGN KEY FK_32D5C2B3C32A47EE');
        $this->addSql('ALTER TABLE routes DROP FOREIGN KEY FK_32D5C2B3C3423909');
        $this->addSql('DROP TABLE route_stops');
        $this->addSql('DROP TABLE routes');
    }
}
