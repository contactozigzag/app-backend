<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260119221552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Only creating notification_preferences table - other tables already exist from Phase 3
        $this->addSql('CREATE TABLE notification_preferences (id INT AUTO_INCREMENT NOT NULL, email_enabled TINYINT NOT NULL, sms_enabled TINYINT NOT NULL, push_enabled TINYINT NOT NULL, notify_on_arriving TINYINT NOT NULL, notify_on_pickup TINYINT NOT NULL, notify_on_dropoff TINYINT NOT NULL, notify_on_route_start TINYINT NOT NULL, notify_on_delay TINYINT NOT NULL, notify_on_cancellation TINYINT NOT NULL, arrival_notification_minutes INT DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_3CAA95B4A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE notification_preferences ADD CONSTRAINT FK_3CAA95B4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification_preferences DROP FOREIGN KEY FK_3CAA95B4A76ED395');
        $this->addSql('DROP TABLE notification_preferences');
    }
}
