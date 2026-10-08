<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Commission rejection with immutable archived orders and deductions, and safe assignment release.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commission ADD rejection_reason TEXT DEFAULT NULL, ADD rejected_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD rejected_by_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_1C650158CBF05FC9 ON commission (rejected_by_id)');
        $this->addSql('ALTER TABLE commission ADD CONSTRAINT commission_rejected_by_fk FOREIGN KEY (rejected_by_id) REFERENCES app_user (id)');
        $this->addSql('CREATE TABLE commission_rejected_order (commission_id INT NOT NULL, order_snapshot_id INT NOT NULL, PRIMARY KEY (commission_id, order_snapshot_id))');
        $this->addSql('CREATE INDEX IDX_8E96B129202D1EB2 ON commission_rejected_order (commission_id)');
        $this->addSql('CREATE INDEX IDX_8E96B129608B46D8 ON commission_rejected_order (order_snapshot_id)');
        $this->addSql('ALTER TABLE commission_rejected_order ADD CONSTRAINT rejected_order_commission_fk FOREIGN KEY (commission_id) REFERENCES commission (id)');
        $this->addSql('ALTER TABLE commission_rejected_order ADD CONSTRAINT rejected_order_snapshot_fk FOREIGN KEY (order_snapshot_id) REFERENCES order_snapshot (id)');
        $this->addSql('CREATE TABLE commission_rejected_adjustment (commission_id INT NOT NULL, adjustment_id INT NOT NULL, PRIMARY KEY (commission_id, adjustment_id))');
        $this->addSql('CREATE INDEX IDX_ACAC2717202D1EB2 ON commission_rejected_adjustment (commission_id)');
        $this->addSql('CREATE INDEX IDX_ACAC27175D6DA33D ON commission_rejected_adjustment (adjustment_id)');
        $this->addSql('ALTER TABLE commission_rejected_adjustment ADD CONSTRAINT rejected_adjustment_commission_fk FOREIGN KEY (commission_id) REFERENCES commission (id)');
        $this->addSql('ALTER TABLE commission_rejected_adjustment ADD CONSTRAINT rejected_adjustment_source_fk FOREIGN KEY (adjustment_id) REFERENCES adjustment (id)');
        $this->addSql("ALTER TABLE commission DROP CONSTRAINT commission_status_check, ADD CONSTRAINT commission_status_check CHECK (status IN ('pending', 'approved', 'paid', 'rejected'))");
        $this->addSql("ALTER TABLE commission DROP CONSTRAINT commission_approval_check, ADD CONSTRAINT commission_approval_check CHECK ((status = 'pending' AND approved_by_id IS NULL AND approved_at IS NULL) OR (status IN ('approved', 'paid') AND approved_by_id IS NOT NULL AND approved_at IS NOT NULL) OR (status = 'rejected' AND ((approved_by_id IS NULL AND approved_at IS NULL) OR (approved_by_id IS NOT NULL AND approved_at IS NOT NULL))))");
        $this->addSql("ALTER TABLE commission ADD CONSTRAINT commission_rejection_check CHECK ((status = 'rejected' AND rejected_by_id IS NOT NULL AND rejected_at IS NOT NULL AND rejection_reason IS NOT NULL AND LENGTH(TRIM(rejection_reason)) >= 10) OR (status <> 'rejected' AND rejected_by_id IS NULL AND rejected_at IS NULL AND rejection_reason IS NULL))");
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_order_snapshot() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' OR (to_jsonb(NEW) - 'commission_id') IS DISTINCT FROM (to_jsonb(OLD) - 'commission_id') THEN
                    RAISE EXCEPTION 'Order snapshots are immutable';
                END IF;
                IF OLD.commission_id IS NOT NULL AND NEW.commission_id IS DISTINCT FROM OLD.commission_id THEN
                    IF NEW.commission_id IS NOT NULL OR NOT EXISTS (
                        SELECT 1 FROM commission_rejected_order a JOIN commission c ON c.id = a.commission_id
                        WHERE a.commission_id = OLD.commission_id AND a.order_snapshot_id = OLD.id AND c.status = 'rejected'
                    ) THEN
                        RAISE EXCEPTION 'Order snapshots are immutable';
                    END IF;
                END IF;
                RETURN NEW;
            END; $$
            SQL);
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION gcom_protect_adjustment() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF (to_jsonb(NEW) - 'commission_id') IS DISTINCT FROM (to_jsonb(OLD) - 'commission_id') THEN
                    RAISE EXCEPTION 'Adjustment sources and deductions are immutable';
                END IF;
                IF OLD.commission_id IS NOT NULL AND NEW.commission_id IS DISTINCT FROM OLD.commission_id THEN
                    IF NEW.commission_id IS NOT NULL OR NOT EXISTS (
                        SELECT 1 FROM commission_rejected_adjustment a JOIN commission c ON c.id = a.commission_id
                        WHERE a.commission_id = OLD.commission_id AND a.adjustment_id = OLD.id AND c.status = 'rejected'
                    ) THEN
                        RAISE EXCEPTION 'Adjustment sources and deductions are immutable';
                    END IF;
                END IF;
                RETURN NEW;
            END; $$
            SQL);
        $this->addSql(<<<'SQL'
            CREATE FUNCTION gcom_protect_rejection() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.status = 'rejected' THEN
                    RAISE EXCEPTION 'Rejected commission history is immutable';
                END IF;
                IF NEW.status = 'rejected' AND (OLD.status = 'paid' OR EXISTS (SELECT 1 FROM payment_link WHERE commission_id = OLD.id)) THEN
                    RAISE EXCEPTION 'Paid commissions cannot be rejected';
                END IF;
                RETURN NEW;
            END; $$
            SQL);
        $this->addSql('CREATE TRIGGER immutable_commission_rejection BEFORE UPDATE ON commission FOR EACH ROW EXECUTE FUNCTION gcom_protect_rejection()');
        $this->addSql('CREATE TRIGGER rejected_orders_append_only BEFORE UPDATE OR DELETE ON commission_rejected_order FOR EACH ROW EXECUTE FUNCTION reject_historical_change()');
        $this->addSql('CREATE TRIGGER rejected_adjustments_append_only BEFORE UPDATE OR DELETE ON commission_rejected_adjustment FOR EACH ROW EXECUTE FUNCTION reject_historical_change()');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Preserves immutable rejection history and archived financial assignments.');
    }
}
