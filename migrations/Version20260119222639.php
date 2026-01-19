<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260119222639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE archived_routes (id INT AUTO_INCREMENT NOT NULL, original_active_route_id INT NOT NULL, route_name VARCHAR(255) NOT NULL, route_type VARCHAR(20) NOT NULL, driver_name VARCHAR(255) NOT NULL, date DATE NOT NULL, status VARCHAR(20) NOT NULL, started_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, total_distance INT DEFAULT NULL, total_duration INT DEFAULT NULL, actual_duration INT DEFAULT NULL, total_stops INT NOT NULL, completed_stops INT NOT NULL, skipped_stops INT NOT NULL, students_picked_up INT NOT NULL, students_dropped_off INT NOT NULL, no_shows INT NOT NULL, on_time_percentage NUMERIC(5, 2) DEFAULT NULL, stop_data JSON DEFAULT NULL, performance_metrics JSON DEFAULT NULL, notes LONGTEXT DEFAULT NULL, archived_at DATETIME NOT NULL, school_id INT NOT NULL, INDEX IDX_7E618BE4C32A47EE (school_id), INDEX idx_archived_date (date), INDEX idx_school_date (school_id, date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE archived_routes ADD CONSTRAINT FK_7E618BE4C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE absences ADD CONSTRAINT FK_F9C0EFFFCB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE absences ADD CONSTRAINT FK_F9C0EFFF71CE806 FOREIGN KEY (reported_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE active_route_stops ADD CONSTRAINT FK_F0354841CE60C539 FOREIGN KEY (active_route_id) REFERENCES active_routes (id)');
        $this->addSql('ALTER TABLE active_route_stops ADD CONSTRAINT FK_F0354841CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE active_route_stops ADD CONSTRAINT FK_F0354841F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE active_routes ADD CONSTRAINT FK_ADEC1BA43BBA0FA9 FOREIGN KEY (route_template_id) REFERENCES routes (id)');
        $this->addSql('ALTER TABLE active_routes ADD CONSTRAINT FK_ADEC1BA4C3423909 FOREIGN KEY (driver_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_6DE30D91CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_6DE30D911F0B9F07 FOREIGN KEY (active_route_stop_id) REFERENCES active_route_stops (id)');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_6DE30D91D05A957B FOREIGN KEY (recorded_by_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE location_updates ADD CONSTRAINT FK_AB0D0094C3423909 FOREIGN KEY (driver_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE location_updates ADD CONSTRAINT FK_AB0D0094CE60C539 FOREIGN KEY (active_route_id) REFERENCES active_routes (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE archived_routes DROP FOREIGN KEY FK_7E618BE4C32A47EE');
        $this->addSql('DROP TABLE archived_routes');
        $this->addSql('ALTER TABLE absences DROP FOREIGN KEY FK_F9C0EFFFCB944F1A');
        $this->addSql('ALTER TABLE absences DROP FOREIGN KEY FK_F9C0EFFF71CE806');
        $this->addSql('ALTER TABLE active_route_stops DROP FOREIGN KEY FK_F0354841CE60C539');
        $this->addSql('ALTER TABLE active_route_stops DROP FOREIGN KEY FK_F0354841CB944F1A');
        $this->addSql('ALTER TABLE active_route_stops DROP FOREIGN KEY FK_F0354841F5B7AF75');
        $this->addSql('ALTER TABLE active_routes DROP FOREIGN KEY FK_ADEC1BA43BBA0FA9');
        $this->addSql('ALTER TABLE active_routes DROP FOREIGN KEY FK_ADEC1BA4C3423909');
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_6DE30D91CB944F1A');
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_6DE30D911F0B9F07');
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_6DE30D91D05A957B');
        $this->addSql('ALTER TABLE location_updates DROP FOREIGN KEY FK_AB0D0094C3423909');
        $this->addSql('ALTER TABLE location_updates DROP FOREIGN KEY FK_AB0D0094CE60C539');
    }
}
