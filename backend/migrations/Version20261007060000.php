<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make manual payment justification optional while preserving payment amount origin.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_link DROP CONSTRAINT payment_amount_origin');
        $this->addSql(<<<'SQL'
            ALTER TABLE payment_link ADD CONSTRAINT payment_amount_origin CHECK (
                calculated_amount > 0 AND (
                    (manual_amount = FALSE AND amount = calculated_amount AND manual_reason IS NULL)
                    OR manual_amount = TRUE
                )
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Manual payments may have been confirmed without a justification.');
    }
}
