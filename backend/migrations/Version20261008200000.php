<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Empresa da instalação e prefixo de novas comissões; mantém códigos existentes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE enterprise (id INT NOT NULL, legal_name VARCHAR(180) NOT NULL, trade_name VARCHAR(120) NOT NULL, cnpj VARCHAR(18) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(30) NOT NULL, address VARCHAR(500) NOT NULL, commission_prefix VARCHAR(20) NOT NULL, PRIMARY KEY (id), CONSTRAINT enterprise_singleton CHECK (id = 1))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE enterprise');
    }
}
