<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optional Winthor routine 749 NUMTRANS and details snapshot.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_link ALTER routine DROP NOT NULL');
        $this->addSql('ALTER TABLE payment_link ALTER reference DROP NOT NULL');
        $this->addSql('ALTER TABLE payment_link ADD winthor_details JSON DEFAULT NULL');
        $this->addSql("ALTER TABLE payment_link ADD CONSTRAINT payment_reference_check CHECK ((routine IS NULL AND reference IS NULL AND verification = 'none') OR (routine = '749' AND reference IS NOT NULL AND verification IN ('manual_reference', 'winthor_lookup')))");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Financial history.');
    }
}
