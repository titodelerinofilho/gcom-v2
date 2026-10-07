<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow finance users to approve their own commissions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commission DROP CONSTRAINT commission_approval_check');
        $this->addSql(<<<'SQL'
            ALTER TABLE commission ADD CONSTRAINT commission_approval_check CHECK (
                (status = 'pending' AND approved_by_id IS NULL AND approved_at IS NULL)
                OR (status IN ('approved', 'paid') AND approved_by_id IS NOT NULL AND approved_at IS NOT NULL)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Finance approval policy has changed.');
    }
}
