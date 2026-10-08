<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Immutable saved report data for reproducible exports and authenticated history links.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE report_snapshot (id VARCHAR(32) NOT NULL, created_by_id INT NOT NULL, kind VARCHAR(20) NOT NULL, date_from VARCHAR(10) NOT NULL, date_to_exclusive VARCHAR(10) NOT NULL, filters JSON NOT NULL, rows JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2C2D8C48B03A8386 ON report_snapshot (created_by_id)');
        $this->addSql('ALTER TABLE report_snapshot ADD CONSTRAINT report_snapshot_created_by_fk FOREIGN KEY (created_by_id) REFERENCES app_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql(<<<'SQL'
            CREATE FUNCTION protect_report_snapshot() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Saved report snapshots are immutable';
            END; $$
            SQL);
        $this->addSql('CREATE TRIGGER report_snapshot_immutable BEFORE UPDATE OR DELETE ON report_snapshot FOR EACH ROW EXECUTE FUNCTION protect_report_snapshot()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE report_snapshot');
        $this->addSql('DROP FUNCTION protect_report_snapshot()');
    }
}
