<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130035039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student ADD identification_number VARCHAR(10) NOT NULL, ADD gender VARCHAR(255) DEFAULT NULL, ADD birthday DATE DEFAULT NULL, ADD medical_history LONGTEXT DEFAULT NULL, ADD additional_info LONGTEXT DEFAULT NULL, ADD emergency_contact VARCHAR(255) DEFAULT NULL, ADD emergency_contact_number VARCHAR(30) DEFAULT NULL, ADD educational_level VARCHAR(255) DEFAULT NULL, ADD grade VARCHAR(255) DEFAULT NULL, CHANGE school_id school_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B723AF33347639A5 ON student (identification_number)');
        $this->addSql('ALTER TABLE user ADD identification_number VARCHAR(10) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649347639A5 ON user (identification_number)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_B723AF33347639A5 ON student');
        $this->addSql('ALTER TABLE student DROP identification_number, DROP gender, DROP birthday, DROP medical_history, DROP additional_info, DROP emergency_contact, DROP emergency_contact_number, DROP educational_level, DROP grade, CHANGE school_id school_id INT NOT NULL');
        $this->addSql('DROP INDEX UNIQ_8D93D649347639A5 ON user');
        $this->addSql('ALTER TABLE user DROP identification_number');
    }
}
