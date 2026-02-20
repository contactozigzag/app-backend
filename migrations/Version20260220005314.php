<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220005314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE idempotency_records (id INT AUTO_INCREMENT NOT NULL, idempotency_key VARCHAR(255) NOT NULL, result LONGTEXT NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_CBC8586C7FD1C147 (idempotency_key), INDEX idx_idempotency_expires_at (expires_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, payment_method VARCHAR(50) DEFAULT NULL, status VARCHAR(50) NOT NULL, payment_provider_id VARCHAR(255) DEFAULT NULL, preference_id VARCHAR(255) DEFAULT NULL, metadata JSON DEFAULT NULL, idempotency_key VARCHAR(36) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, paid_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL, refunded_amount NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_6D28840D7FD1C147 (idempotency_key), INDEX IDX_6D28840DA76ED395 (user_id), INDEX idx_payments_user_status (user_id, status), INDEX idx_payments_provider_id (payment_provider_id), INDEX idx_payments_idempotency (idempotency_key), INDEX idx_payments_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment_student (payment_id INT NOT NULL, student_id INT NOT NULL, INDEX IDX_AE6AA1D94C3A3BB (payment_id), INDEX IDX_AE6AA1D9CB944F1A (student_id), PRIMARY KEY (payment_id, student_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment_transaction (id INT AUTO_INCREMENT NOT NULL, event_type VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, provider_response JSON DEFAULT NULL, idempotency_key VARCHAR(36) DEFAULT NULL, created_at DATETIME NOT NULL, notes LONGTEXT DEFAULT NULL, ip_address VARCHAR(50) DEFAULT NULL, user_agent LONGTEXT DEFAULT NULL, payment_id INT NOT NULL, INDEX idx_transactions_payment_id (payment_id), INDEX idx_transactions_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE subscription (id INT AUTO_INCREMENT NOT NULL, plan_type VARCHAR(100) NOT NULL, status VARCHAR(50) NOT NULL, amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, billing_cycle VARCHAR(50) NOT NULL, next_billing_date DATE NOT NULL, mercado_pago_subscription_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, cancelled_at DATETIME DEFAULT NULL, notes LONGTEXT DEFAULT NULL, failed_payment_count INT DEFAULT 0 NOT NULL, last_payment_attempt_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_A3C664D3A76ED395 (user_id), INDEX idx_subscriptions_user_status (user_id, status), INDEX idx_subscriptions_next_billing (next_billing_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE subscription_student (subscription_id INT NOT NULL, student_id INT NOT NULL, INDEX IDX_C4364FA89A1887DC (subscription_id), INDEX IDX_C4364FA8CB944F1A (student_id), PRIMARY KEY (subscription_id, student_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE payment_student ADD CONSTRAINT FK_AE6AA1D94C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment_student ADD CONSTRAINT FK_AE6AA1D9CB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment_transaction ADD CONSTRAINT FK_84BBD50B4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id)');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE subscription_student ADD CONSTRAINT FK_C4364FA89A1887DC FOREIGN KEY (subscription_id) REFERENCES subscription (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subscription_student ADD CONSTRAINT FK_C4364FA8CB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE route_stops CHANGE is_confirmed is_confirmed TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DA76ED395');
        $this->addSql('ALTER TABLE payment_student DROP FOREIGN KEY FK_AE6AA1D94C3A3BB');
        $this->addSql('ALTER TABLE payment_student DROP FOREIGN KEY FK_AE6AA1D9CB944F1A');
        $this->addSql('ALTER TABLE payment_transaction DROP FOREIGN KEY FK_84BBD50B4C3A3BB');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D3A76ED395');
        $this->addSql('ALTER TABLE subscription_student DROP FOREIGN KEY FK_C4364FA89A1887DC');
        $this->addSql('ALTER TABLE subscription_student DROP FOREIGN KEY FK_C4364FA8CB944F1A');
        $this->addSql('DROP TABLE idempotency_records');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE payment_student');
        $this->addSql('DROP TABLE payment_transaction');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP TABLE subscription_student');
        $this->addSql('ALTER TABLE route_stops CHANGE is_confirmed is_confirmed TINYINT DEFAULT 0 NOT NULL');
    }
}
