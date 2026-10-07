<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contextual price mappings and immutable, deduplicated Winthor return sources.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE adjustment ADD source_key VARCHAR(100) DEFAULT NULL, ADD source_snapshot JSON DEFAULT NULL');
        $this->addSql("ALTER TABLE adjustment DROP CONSTRAINT adjustment_amount_check, ADD CONSTRAINT adjustment_amount_check CHECK (amount > 0 AND type IN ('debt', 'return', 'cancellation'))");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ADJUSTMENT_SOURCE ON adjustment (source_key)');
        $this->addSql(<<<'SQL'
            CREATE FUNCTION gcom_protect_adjustment() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF (to_jsonb(NEW) - 'commission_id') IS DISTINCT FROM (to_jsonb(OLD) - 'commission_id')
                   OR (OLD.commission_id IS NOT NULL AND NEW.commission_id IS DISTINCT FROM OLD.commission_id) THEN
                    RAISE EXCEPTION 'Adjustment sources and deductions are immutable';
                END IF;
                RETURN NEW;
            END; $$
            SQL);
        $this->addSql('CREATE TRIGGER immutable_adjustment BEFORE UPDATE ON adjustment FOR EACH ROW EXECUTE FUNCTION gcom_protect_adjustment()');
        // Pernambuco pairing confirmed by the user: PSD 31 / PSCF 33.
        $settings = ['percentage' => '80.0000', 'basis' => 'margin_psd', 'subtractFreight' => true, 'applyReferenceDiscount' => false, 'atgPercentage' => '80.0000', 'returnPercentage' => '80.0000', 'atgReturnPercentage' => '100.0000', 'priceContexts' => []];
        foreach ([[1, 2], [5, 6], [7, 8], [30, 32], [31, 33]] as [$psd, $pscf]) {
            foreach ([$psd, $pscf] as $region) {
                $settings['priceContexts'][] = ['branch' => '*', 'orderRegion' => $region, 'psdRegion' => $psd, 'pscfRegion' => $pscf];
            }
        }
        // Preserve existing custom formula settings while replacing the single-region selection.
        $this->addSql(<<<'SQL'
            INSERT INTO commission_rule (settings, reason, created_at)
            SELECT ((settings::jsonb - 'psdRegion') || :settings::jsonb ||
                jsonb_build_object('percentage', settings->>'percentage', 'basis', settings->>'basis',
                    'subtractFreight', (settings->>'subtractFreight')::boolean,
                    'applyReferenceDiscount', (settings->>'applyReferenceDiscount')::boolean))::json,
                'Pareamentos do legado por tabela, com prioridade para filial específica. Pernambuco confirmado: 31/33. Validar exceções por filial antes de operar.', CURRENT_TIMESTAMP
            FROM commission_rule ORDER BY id DESC LIMIT 1
            SQL, ['settings' => json_encode($settings, \JSON_THROW_ON_ERROR)]);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Contains immutable financial sources.');
    }
}
