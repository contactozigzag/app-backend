<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223223833 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat_messages (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, sent_at DATETIME NOT NULL, read_by JSON NOT NULL, created_at DATETIME NOT NULL, alert_id INT NOT NULL, sender_id INT NOT NULL, INDEX IDX_EF20C9A693035F72 (alert_id), INDEX IDX_EF20C9A6F624B39D (sender_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE driver_alerts (id INT AUTO_INCREMENT NOT NULL, alert_id VARCHAR(36) NOT NULL, location_lat NUMERIC(10, 6) NOT NULL, location_lng NUMERIC(10, 6) NOT NULL, status VARCHAR(255) NOT NULL, triggered_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, nearby_driver_ids JSON NOT NULL, created_at DATETIME NOT NULL, distressed_driver_id INT NOT NULL, route_session_id INT DEFAULT NULL, resolved_by_id INT DEFAULT NULL, responding_driver_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_B2251ED393035F72 (alert_id), INDEX IDX_B2251ED36A46C2B8 (distressed_driver_id), INDEX IDX_B2251ED36F482638 (route_session_id), INDEX IDX_B2251ED36713A32B (resolved_by_id), INDEX IDX_B2251ED3C9AC8D86 (responding_driver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE special_event_route_stops (id INT AUTO_INCREMENT NOT NULL, stop_order INT NOT NULL, estimated_arrival_time INT DEFAULT NULL, status VARCHAR(20) NOT NULL, is_student_ready TINYINT NOT NULL, ready_at DATETIME DEFAULT NULL, geofence_radius INT NOT NULL, created_at DATETIME NOT NULL, special_event_route_id INT NOT NULL, student_id INT NOT NULL, address_id INT NOT NULL, INDEX IDX_7324C72892ACB056 (special_event_route_id), INDEX IDX_7324C728CB944F1A (student_id), INDEX IDX_7324C728F5B7AF75 (address_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE special_event_routes (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, event_date DATE NOT NULL, event_type VARCHAR(255) NOT NULL, route_mode VARCHAR(255) NOT NULL, departure_mode VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, outbound_departure_time DATETIME DEFAULT NULL, return_departure_time DATETIME DEFAULT NULL, current_latitude NUMERIC(10, 6) DEFAULT NULL, current_longitude NUMERIC(10, 6) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, school_id INT NOT NULL, event_location_id INT DEFAULT NULL, assigned_driver_id INT DEFAULT NULL, assigned_vehicle_id INT DEFAULT NULL, INDEX IDX_D88A1BE8C32A47EE (school_id), INDEX IDX_D88A1BE8ADC4F20E (event_location_id), INDEX IDX_D88A1BE8BAE38CAB (assigned_driver_id), INDEX IDX_D88A1BE86CF274A0 (assigned_vehicle_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE special_event_route_student (special_event_route_id INT NOT NULL, student_id INT NOT NULL, INDEX IDX_5812FB4792ACB056 (special_event_route_id), INDEX IDX_5812FB47CB944F1A (student_id), PRIMARY KEY (special_event_route_id, student_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE chat_messages ADD CONSTRAINT FK_EF20C9A693035F72 FOREIGN KEY (alert_id) REFERENCES driver_alerts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_messages ADD CONSTRAINT FK_EF20C9A6F624B39D FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE driver_alerts ADD CONSTRAINT FK_B2251ED36A46C2B8 FOREIGN KEY (distressed_driver_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE driver_alerts ADD CONSTRAINT FK_B2251ED36F482638 FOREIGN KEY (route_session_id) REFERENCES active_routes (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE driver_alerts ADD CONSTRAINT FK_B2251ED36713A32B FOREIGN KEY (resolved_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE driver_alerts ADD CONSTRAINT FK_B2251ED3C9AC8D86 FOREIGN KEY (responding_driver_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE special_event_route_stops ADD CONSTRAINT FK_7324C72892ACB056 FOREIGN KEY (special_event_route_id) REFERENCES special_event_routes (id)');
        $this->addSql('ALTER TABLE special_event_route_stops ADD CONSTRAINT FK_7324C728CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE special_event_route_stops ADD CONSTRAINT FK_7324C728F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE special_event_routes ADD CONSTRAINT FK_D88A1BE8C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE special_event_routes ADD CONSTRAINT FK_D88A1BE8ADC4F20E FOREIGN KEY (event_location_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE special_event_routes ADD CONSTRAINT FK_D88A1BE8BAE38CAB FOREIGN KEY (assigned_driver_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE special_event_routes ADD CONSTRAINT FK_D88A1BE86CF274A0 FOREIGN KEY (assigned_vehicle_id) REFERENCES vehicle (id)');
        $this->addSql('ALTER TABLE special_event_route_student ADD CONSTRAINT FK_5812FB4792ACB056 FOREIGN KEY (special_event_route_id) REFERENCES special_event_routes (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE special_event_route_student ADD CONSTRAINT FK_5812FB47CB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE driver CHANGE mp_token_expires_at mp_token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY `FK_payment_driver`');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DC3423909 FOREIGN KEY (driver_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE payment RENAME INDEX idx_payments_driver TO IDX_6D28840DC3423909');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_messages DROP FOREIGN KEY FK_EF20C9A693035F72');
        $this->addSql('ALTER TABLE chat_messages DROP FOREIGN KEY FK_EF20C9A6F624B39D');
        $this->addSql('ALTER TABLE driver_alerts DROP FOREIGN KEY FK_B2251ED36A46C2B8');
        $this->addSql('ALTER TABLE driver_alerts DROP FOREIGN KEY FK_B2251ED36F482638');
        $this->addSql('ALTER TABLE driver_alerts DROP FOREIGN KEY FK_B2251ED36713A32B');
        $this->addSql('ALTER TABLE driver_alerts DROP FOREIGN KEY FK_B2251ED3C9AC8D86');
        $this->addSql('ALTER TABLE special_event_route_stops DROP FOREIGN KEY FK_7324C72892ACB056');
        $this->addSql('ALTER TABLE special_event_route_stops DROP FOREIGN KEY FK_7324C728CB944F1A');
        $this->addSql('ALTER TABLE special_event_route_stops DROP FOREIGN KEY FK_7324C728F5B7AF75');
        $this->addSql('ALTER TABLE special_event_routes DROP FOREIGN KEY FK_D88A1BE8C32A47EE');
        $this->addSql('ALTER TABLE special_event_routes DROP FOREIGN KEY FK_D88A1BE8ADC4F20E');
        $this->addSql('ALTER TABLE special_event_routes DROP FOREIGN KEY FK_D88A1BE8BAE38CAB');
        $this->addSql('ALTER TABLE special_event_routes DROP FOREIGN KEY FK_D88A1BE86CF274A0');
        $this->addSql('ALTER TABLE special_event_route_student DROP FOREIGN KEY FK_5812FB4792ACB056');
        $this->addSql('ALTER TABLE special_event_route_student DROP FOREIGN KEY FK_5812FB47CB944F1A');
        $this->addSql('DROP TABLE chat_messages');
        $this->addSql('DROP TABLE driver_alerts');
        $this->addSql('DROP TABLE special_event_route_stops');
        $this->addSql('DROP TABLE special_event_routes');
        $this->addSql('DROP TABLE special_event_route_student');
        $this->addSql('ALTER TABLE driver CHANGE mp_token_expires_at mp_token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DC3423909');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT `FK_payment_driver` FOREIGN KEY (driver_id) REFERENCES driver (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE payment RENAME INDEX idx_6d28840dc3423909 TO idx_payments_driver');
    }
}
