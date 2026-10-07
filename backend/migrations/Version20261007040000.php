<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve calculated and paid amounts with explicit manual payment justification.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_link ADD calculated_amount NUMERIC(18, 2), ADD manual_amount BOOLEAN, ADD manual_reason TEXT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE payment_link p SET calculated_amount = c.net_amount,
                manual_amount = p.amount <> c.net_amount,
                manual_reason = CASE WHEN p.amount <> c.net_amount
                    THEN 'Pagamento histórico divergente identificado na migração.' ELSE NULL END
            FROM commission c WHERE c.id = p.commission_id
            SQL);
        $this->addSql('ALTER TABLE payment_link ALTER calculated_amount SET NOT NULL, ALTER manual_amount SET NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE payment_link ADD CONSTRAINT payment_amount_origin CHECK (
                calculated_amount > 0 AND (
                    (manual_amount = FALSE AND amount = calculated_amount AND manual_reason IS NULL)
                    OR (manual_amount = TRUE AND manual_reason IS NOT NULL AND LENGTH(BTRIM(manual_reason)) >= 10)
                )
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE FUNCTION gcom_protect_payment_amounts() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.amount IS DISTINCT FROM OLD.amount
                    OR NEW.calculated_amount IS DISTINCT FROM OLD.calculated_amount
                    OR NEW.manual_amount IS DISTINCT FROM OLD.manual_amount
                    OR NEW.manual_reason IS DISTINCT FROM OLD.manual_reason THEN
                    RAISE EXCEPTION 'Confirmed payment amounts and justification are immutable';
                END IF;
                RETURN NEW;
            END;
            $$
            SQL);
        $this->addSql('CREATE TRIGGER immutable_payment_amounts BEFORE UPDATE ON payment_link FOR EACH ROW EXECUTE FUNCTION gcom_protect_payment_amounts()');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Confirmed payment financial history.');
    }
}
