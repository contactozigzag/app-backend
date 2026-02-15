<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Payment system tables migration
 */
final class Version20260214000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create payment, subscription, payment_transaction, and idempotency_records tables';
    }

    public function up(Schema $schema): void
    {
        // Payment table
        $this->addSql('CREATE TABLE payment (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            amount NUMERIC(10, 2) NOT NULL,
            currency VARCHAR(3) NOT NULL,
            payment_method VARCHAR(50) DEFAULT NULL,
            status VARCHAR(50) NOT NULL,
            payment_provider_id VARCHAR(255) DEFAULT NULL,
            preference_id VARCHAR(255) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            idempotency_key VARCHAR(36) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            paid_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            refunded_amount NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL,
            INDEX idx_payments_user_status (user_id, status),
            INDEX idx_payments_provider_id (payment_provider_id),
            INDEX idx_payments_idempotency (idempotency_key),
            INDEX idx_payments_created_at (created_at),
            UNIQUE INDEX UNIQ_6D28840D7C04E3E7 (idempotency_key),
            INDEX IDX_6D28840DA76ED395 (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');

        // Payment-Student join table
        $this->addSql('CREATE TABLE payment_student (
            payment_id INT NOT NULL,
            student_id INT NOT NULL,
            INDEX IDX_8B77BF7C4C3A3BB (payment_id),
            INDEX IDX_8B77BF7CCB944F1A (student_id),
            PRIMARY KEY(payment_id, student_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE payment_student ADD CONSTRAINT FK_8B77BF7C4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment_student ADD CONSTRAINT FK_8B77BF7CCB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');

        // Subscription table
        $this->addSql('CREATE TABLE subscription (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            plan_type VARCHAR(100) NOT NULL,
            status VARCHAR(50) NOT NULL,
            amount NUMERIC(10, 2) NOT NULL,
            currency VARCHAR(3) NOT NULL,
            billing_cycle VARCHAR(50) NOT NULL,
            next_billing_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            mercado_pago_subscription_id VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            cancelled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            notes LONGTEXT DEFAULT NULL,
            failed_payment_count INT DEFAULT 0 NOT NULL,
            last_payment_attempt_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_subscriptions_user_status (user_id, status),
            INDEX idx_subscriptions_next_billing (next_billing_date),
            INDEX IDX_A3C664D3A76ED395 (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');

        // Subscription-Student join table
        $this->addSql('CREATE TABLE subscription_student (
            subscription_id INT NOT NULL,
            student_id INT NOT NULL,
            INDEX IDX_F3D1D0169A1887DC (subscription_id),
            INDEX IDX_F3D1D016CB944F1A (student_id),
            PRIMARY KEY(subscription_id, student_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE subscription_student ADD CONSTRAINT FK_F3D1D0169A1887DC FOREIGN KEY (subscription_id) REFERENCES subscription (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subscription_student ADD CONSTRAINT FK_F3D1D016CB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');

        // Payment Transaction table
        $this->addSql('CREATE TABLE payment_transaction (
            id INT AUTO_INCREMENT NOT NULL,
            payment_id INT NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            provider_response JSON DEFAULT NULL,
            idempotency_key VARCHAR(36) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            notes LONGTEXT DEFAULT NULL,
            ip_address VARCHAR(50) DEFAULT NULL,
            user_agent LONGTEXT DEFAULT NULL,
            INDEX idx_transactions_payment_id (payment_id),
            INDEX idx_transactions_created_at (created_at),
            INDEX IDX_41152C4C4C3A3BB (payment_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE payment_transaction ADD CONSTRAINT FK_41152C4C4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id)');

        // Idempotency records table
        $this->addSql('CREATE TABLE idempotency_records (
            id INT AUTO_INCREMENT NOT NULL,
            idempotency_key VARCHAR(36) NOT NULL,
            result LONGTEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_idempotency_key (idempotency_key),
            INDEX idx_expires_at (expires_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS payment_transaction');
        $this->addSql('DROP TABLE IF EXISTS subscription_student');
        $this->addSql('DROP TABLE IF EXISTS subscription');
        $this->addSql('DROP TABLE IF EXISTS payment_student');
        $this->addSql('DROP TABLE IF EXISTS payment');
        $this->addSql('DROP TABLE IF EXISTS idempotency_records');
    }
}
