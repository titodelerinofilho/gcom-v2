<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Logo da empresa armazenada no volume local; preserva dados existentes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enterprise ADD logo_filename VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enterprise DROP logo_filename');
    }
}
