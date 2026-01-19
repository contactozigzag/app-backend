<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260115191852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE address (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(30) NOT NULL, street_address VARCHAR(100) NOT NULL, city VARCHAR(100) NOT NULL, state VARCHAR(100) NOT NULL, country VARCHAR(100) NOT NULL, postal_code VARCHAR(10) NOT NULL, latitude NUMERIC(10, 8) NOT NULL, longitude NUMERIC(11, 8) NOT NULL, place_id VARCHAR(255) DEFAULT NULL, is_default TINYINT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_D4E6F81A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE driver (id INT AUTO_INCREMENT NOT NULL, license_number VARCHAR(50) DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_11667CD9A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE school (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE student (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, school_id INT NOT NULL, INDEX IDX_B723AF33C32A47EE (school_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE student_user (student_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_B2B0AD91CB944F1A (student_id), INDEX IDX_B2B0AD91A76ED395 (user_id), PRIMARY KEY (student_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(80) NOT NULL, last_name VARCHAR(80) NOT NULL, phone_number VARCHAR(20) NOT NULL, school_id INT DEFAULT NULL, INDEX IDX_8D93D649C32A47EE (school_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE address ADD CONSTRAINT FK_D4E6F81A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE driver ADD CONSTRAINT FK_11667CD9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE student_user ADD CONSTRAINT FK_B2B0AD91CB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_user ADD CONSTRAINT FK_B2B0AD91A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE address DROP FOREIGN KEY FK_D4E6F81A76ED395');
        $this->addSql('ALTER TABLE driver DROP FOREIGN KEY FK_11667CD9A76ED395');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33C32A47EE');
        $this->addSql('ALTER TABLE student_user DROP FOREIGN KEY FK_B2B0AD91CB944F1A');
        $this->addSql('ALTER TABLE student_user DROP FOREIGN KEY FK_B2B0AD91A76ED395');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649C32A47EE');
        $this->addSql('DROP TABLE address');
        $this->addSql('DROP TABLE driver');
        $this->addSql('DROP TABLE school');
        $this->addSql('DROP TABLE student');
        $this->addSql('DROP TABLE student_user');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
