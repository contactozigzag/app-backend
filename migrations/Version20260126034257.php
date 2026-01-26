<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260126034257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE absences (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, type VARCHAR(20) NOT NULL, reason VARCHAR(50) NOT NULL, notes LONGTEXT DEFAULT NULL, route_recalculated TINYINT NOT NULL, created_at DATETIME NOT NULL, student_id INT NOT NULL, reported_by_id INT NOT NULL, INDEX IDX_F9C0EFFFCB944F1A (student_id), INDEX IDX_F9C0EFFF71CE806 (reported_by_id), INDEX idx_student_date (student_id, date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE active_route_stops (id INT AUTO_INCREMENT NOT NULL, stop_order INT NOT NULL, status VARCHAR(20) NOT NULL, arrived_at DATETIME DEFAULT NULL, picked_up_at DATETIME DEFAULT NULL, dropped_off_at DATETIME DEFAULT NULL, estimated_arrival_time INT DEFAULT NULL, geofence_radius INT NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, active_route_id INT NOT NULL, student_id INT NOT NULL, address_id INT NOT NULL, INDEX IDX_F0354841CE60C539 (active_route_id), INDEX IDX_F0354841CB944F1A (student_id), INDEX IDX_F0354841F5B7AF75 (address_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE active_routes (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, status VARCHAR(20) NOT NULL, started_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, current_latitude NUMERIC(10, 6) DEFAULT NULL, current_longitude NUMERIC(10, 6) DEFAULT NULL, total_distance INT DEFAULT NULL, total_duration INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, route_template_id INT NOT NULL, driver_id INT NOT NULL, INDEX IDX_ADEC1BA43BBA0FA9 (route_template_id), INDEX IDX_ADEC1BA4C3423909 (driver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE address (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(30) NOT NULL, street_address VARCHAR(100) NOT NULL, city VARCHAR(100) NOT NULL, state VARCHAR(100) NOT NULL, country VARCHAR(100) NOT NULL, postal_code VARCHAR(10) NOT NULL, latitude NUMERIC(10, 8) NOT NULL, longitude NUMERIC(11, 8) NOT NULL, place_id VARCHAR(255) DEFAULT NULL, is_default TINYINT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_D4E6F81A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE archived_routes (id INT AUTO_INCREMENT NOT NULL, original_active_route_id INT NOT NULL, route_name VARCHAR(255) NOT NULL, route_type VARCHAR(20) NOT NULL, driver_name VARCHAR(255) NOT NULL, date DATE NOT NULL, status VARCHAR(20) NOT NULL, started_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, total_distance INT DEFAULT NULL, total_duration INT DEFAULT NULL, actual_duration INT DEFAULT NULL, total_stops INT NOT NULL, completed_stops INT NOT NULL, skipped_stops INT NOT NULL, students_picked_up INT NOT NULL, students_dropped_off INT NOT NULL, no_shows INT NOT NULL, on_time_percentage NUMERIC(5, 2) DEFAULT NULL, stop_data JSON DEFAULT NULL, performance_metrics JSON DEFAULT NULL, notes LONGTEXT DEFAULT NULL, archived_at DATETIME NOT NULL, school_id INT NOT NULL, INDEX IDX_7E618BE4C32A47EE (school_id), INDEX idx_archived_date (date), INDEX idx_school_date (school_id, date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE attendance (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, status VARCHAR(20) NOT NULL, picked_up_at DATETIME DEFAULT NULL, dropped_off_at DATETIME DEFAULT NULL, pickup_latitude NUMERIC(10, 6) DEFAULT NULL, pickup_longitude NUMERIC(10, 6) DEFAULT NULL, dropoff_latitude NUMERIC(10, 6) DEFAULT NULL, dropoff_longitude NUMERIC(10, 6) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, student_id INT NOT NULL, active_route_stop_id INT NOT NULL, recorded_by_id INT DEFAULT NULL, INDEX IDX_6DE30D91CB944F1A (student_id), INDEX IDX_6DE30D91D05A957B (recorded_by_id), INDEX idx_student_date (student_id, date), INDEX idx_route_stop (active_route_stop_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE driver (id INT AUTO_INCREMENT NOT NULL, license_number VARCHAR(50) DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_11667CD9A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE location_updates (id INT AUTO_INCREMENT NOT NULL, latitude NUMERIC(10, 6) NOT NULL, longitude NUMERIC(10, 6) NOT NULL, speed NUMERIC(5, 2) DEFAULT NULL, heading NUMERIC(5, 2) DEFAULT NULL, accuracy NUMERIC(6, 2) DEFAULT NULL, timestamp DATETIME NOT NULL, created_at DATETIME NOT NULL, driver_id INT NOT NULL, active_route_id INT DEFAULT NULL, INDEX IDX_AB0D0094C3423909 (driver_id), INDEX IDX_AB0D0094CE60C539 (active_route_id), INDEX idx_driver_created (driver_id, created_at), INDEX idx_route_created (active_route_id, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification_preferences (id INT AUTO_INCREMENT NOT NULL, email_enabled TINYINT NOT NULL, sms_enabled TINYINT NOT NULL, push_enabled TINYINT NOT NULL, notify_on_arriving TINYINT NOT NULL, notify_on_pickup TINYINT NOT NULL, notify_on_dropoff TINYINT NOT NULL, notify_on_route_start TINYINT NOT NULL, notify_on_delay TINYINT NOT NULL, notify_on_cancellation TINYINT NOT NULL, arrival_notification_minutes INT DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_3CAA95B4A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE route_stops (id INT AUTO_INCREMENT NOT NULL, stop_order INT NOT NULL, estimated_arrival_time INT DEFAULT NULL, geofence_radius INT NOT NULL, notes LONGTEXT DEFAULT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, route_id INT NOT NULL, student_id INT NOT NULL, address_id INT NOT NULL, INDEX IDX_A4CABD0A34ECB4E6 (route_id), INDEX IDX_A4CABD0ACB944F1A (student_id), INDEX IDX_A4CABD0AF5B7AF75 (address_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE routes (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, start_latitude NUMERIC(10, 6) NOT NULL, start_longitude NUMERIC(10, 6) NOT NULL, end_latitude NUMERIC(10, 6) NOT NULL, end_longitude NUMERIC(10, 6) NOT NULL, estimated_duration INT DEFAULT NULL, estimated_distance INT DEFAULT NULL, polyline LONGTEXT DEFAULT NULL, is_active TINYINT NOT NULL, is_template TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, school_id INT NOT NULL, driver_id INT DEFAULT NULL, INDEX IDX_32D5C2B3C32A47EE (school_id), INDEX IDX_32D5C2B3C3423909 (driver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE school (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE student (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, school_id INT NOT NULL, INDEX IDX_B723AF33C32A47EE (school_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE student_user (student_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_B2B0AD91CB944F1A (student_id), INDEX IDX_B2B0AD91A76ED395 (user_id), PRIMARY KEY (student_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(80) NOT NULL, last_name VARCHAR(80) NOT NULL, phone_number VARCHAR(20) NOT NULL, school_id INT DEFAULT NULL, INDEX IDX_8D93D649C32A47EE (school_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE absences ADD CONSTRAINT FK_F9C0EFFFCB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE absences ADD CONSTRAINT FK_F9C0EFFF71CE806 FOREIGN KEY (reported_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE active_route_stops ADD CONSTRAINT FK_F0354841CE60C539 FOREIGN KEY (active_route_id) REFERENCES active_routes (id)');
        $this->addSql('ALTER TABLE active_route_stops ADD CONSTRAINT FK_F0354841CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE active_route_stops ADD CONSTRAINT FK_F0354841F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE active_routes ADD CONSTRAINT FK_ADEC1BA43BBA0FA9 FOREIGN KEY (route_template_id) REFERENCES routes (id)');
        $this->addSql('ALTER TABLE active_routes ADD CONSTRAINT FK_ADEC1BA4C3423909 FOREIGN KEY (driver_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE address ADD CONSTRAINT FK_D4E6F81A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE archived_routes ADD CONSTRAINT FK_7E618BE4C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_6DE30D91CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_6DE30D911F0B9F07 FOREIGN KEY (active_route_stop_id) REFERENCES active_route_stops (id)');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_6DE30D91D05A957B FOREIGN KEY (recorded_by_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE driver ADD CONSTRAINT FK_11667CD9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE location_updates ADD CONSTRAINT FK_AB0D0094C3423909 FOREIGN KEY (driver_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE location_updates ADD CONSTRAINT FK_AB0D0094CE60C539 FOREIGN KEY (active_route_id) REFERENCES active_routes (id)');
        $this->addSql('ALTER TABLE notification_preferences ADD CONSTRAINT FK_3CAA95B4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE route_stops ADD CONSTRAINT FK_A4CABD0A34ECB4E6 FOREIGN KEY (route_id) REFERENCES routes (id)');
        $this->addSql('ALTER TABLE route_stops ADD CONSTRAINT FK_A4CABD0ACB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE route_stops ADD CONSTRAINT FK_A4CABD0AF5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE routes ADD CONSTRAINT FK_32D5C2B3C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE routes ADD CONSTRAINT FK_32D5C2B3C3423909 FOREIGN KEY (driver_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE student_user ADD CONSTRAINT FK_B2B0AD91CB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_user ADD CONSTRAINT FK_B2B0AD91A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absences DROP FOREIGN KEY FK_F9C0EFFFCB944F1A');
        $this->addSql('ALTER TABLE absences DROP FOREIGN KEY FK_F9C0EFFF71CE806');
        $this->addSql('ALTER TABLE active_route_stops DROP FOREIGN KEY FK_F0354841CE60C539');
        $this->addSql('ALTER TABLE active_route_stops DROP FOREIGN KEY FK_F0354841CB944F1A');
        $this->addSql('ALTER TABLE active_route_stops DROP FOREIGN KEY FK_F0354841F5B7AF75');
        $this->addSql('ALTER TABLE active_routes DROP FOREIGN KEY FK_ADEC1BA43BBA0FA9');
        $this->addSql('ALTER TABLE active_routes DROP FOREIGN KEY FK_ADEC1BA4C3423909');
        $this->addSql('ALTER TABLE address DROP FOREIGN KEY FK_D4E6F81A76ED395');
        $this->addSql('ALTER TABLE archived_routes DROP FOREIGN KEY FK_7E618BE4C32A47EE');
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_6DE30D91CB944F1A');
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_6DE30D911F0B9F07');
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_6DE30D91D05A957B');
        $this->addSql('ALTER TABLE driver DROP FOREIGN KEY FK_11667CD9A76ED395');
        $this->addSql('ALTER TABLE location_updates DROP FOREIGN KEY FK_AB0D0094C3423909');
        $this->addSql('ALTER TABLE location_updates DROP FOREIGN KEY FK_AB0D0094CE60C539');
        $this->addSql('ALTER TABLE notification_preferences DROP FOREIGN KEY FK_3CAA95B4A76ED395');
        $this->addSql('ALTER TABLE route_stops DROP FOREIGN KEY FK_A4CABD0A34ECB4E6');
        $this->addSql('ALTER TABLE route_stops DROP FOREIGN KEY FK_A4CABD0ACB944F1A');
        $this->addSql('ALTER TABLE route_stops DROP FOREIGN KEY FK_A4CABD0AF5B7AF75');
        $this->addSql('ALTER TABLE routes DROP FOREIGN KEY FK_32D5C2B3C32A47EE');
        $this->addSql('ALTER TABLE routes DROP FOREIGN KEY FK_32D5C2B3C3423909');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33C32A47EE');
        $this->addSql('ALTER TABLE student_user DROP FOREIGN KEY FK_B2B0AD91CB944F1A');
        $this->addSql('ALTER TABLE student_user DROP FOREIGN KEY FK_B2B0AD91A76ED395');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649C32A47EE');
        $this->addSql('DROP TABLE absences');
        $this->addSql('DROP TABLE active_route_stops');
        $this->addSql('DROP TABLE active_routes');
        $this->addSql('DROP TABLE address');
        $this->addSql('DROP TABLE archived_routes');
        $this->addSql('DROP TABLE attendance');
        $this->addSql('DROP TABLE driver');
        $this->addSql('DROP TABLE location_updates');
        $this->addSql('DROP TABLE notification_preferences');
        $this->addSql('DROP TABLE route_stops');
        $this->addSql('DROP TABLE routes');
        $this->addSql('DROP TABLE school');
        $this->addSql('DROP TABLE student');
        $this->addSql('DROP TABLE student_user');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
