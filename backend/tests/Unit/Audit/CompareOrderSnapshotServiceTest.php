<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit;

use App\Entity\Order\OrderSnapshot;
use App\Service\Audit\CompareOrderSnapshotService;
use PHPUnit\Framework\TestCase;

final class CompareOrderSnapshotServiceTest extends TestCase
{
    private function source(): array
    {
        return ['header' => ['NUMPED' => '123', 'CODCLI' => '100', 'NUMNOTA' => '900', 'VLTOTAL' => '100.00', 'COMMISSION_INVOICES' => []],
            'items' => [['CODPROD' => '200', 'NUMSEQ' => '1', 'DESCRICAO' => 'Produto', 'QT' => '1.000000', 'PVENDA' => '100.00', 'COMMISSION_PRICES' => ['1' => '80']]],
            'invoices' => []];
    }

    public function testNumericFormattingAndCurrentPriceTablesDoNotChangeTheSavedOrder(): void
    {
        $source = $this->source();

        $snapshot = new OrderSnapshot($source['header'], 'Cliente', $source['items']);

        $current = $source;
        $current['items'][0]['QT'] = '1';
        $current['items'][0]['COMMISSION_PRICES'] = ['1' => '90'];

        $output = new CompareOrderSnapshotService()->compare($snapshot, $current);

        self::assertSame('unchanged', $output->status);
        self::assertSame([], $output->changes);
        self::assertSame($source['items'][0], $snapshot->getItems()->first()->getRaw());
    }

    public function testMissingOrdersAndRemovedProductsAreReported(): void
    {
        $source = $this->source();

        $snapshot = new OrderSnapshot($source['header'], 'Cliente', $source['items']);

        $output = new CompareOrderSnapshotService()
            ->compare($snapshot, ['header' => null, 'items' => [], 'invoices' => []]);

        self::assertSame('missing', $output->status);
        self::assertCount(2, $output->changes);
        self::assertSame('Removido', $output->changes[1]->current);
    }

    public function testNewProductsAndInvoicesAreReported(): void
    {
        $source = $this->source();

        $snapshot = new OrderSnapshot($source['header'], 'Cliente', []);

        $source['invoices'] = [['NUMTRANSVENDA' => '700', 'NUMNOTA' => '900', 'DTCANCEL' => null]];

        $output = new CompareOrderSnapshotService()->compare($snapshot, $source);

        self::assertSame('changed', $output->status);
        self::assertCount(2, $output->changes);
        self::assertSame('Adicionado', $output->changes[0]->current);
        self::assertSame('Nota fiscal', $output->changes[1]->section);
    }

    public function testOldSnapshotsDoNotPretendToHaveAnInvoiceBaseline(): void
    {
        $source = $this->source();

        unset($source['header']['COMMISSION_INVOICES']);

        $snapshot = new OrderSnapshot($source['header'], 'Cliente', $source['items']);

        $source['invoices'] = [['NUMNOTA' => '900', 'DTCANCEL' => '2026-10-08']];

        $output = new CompareOrderSnapshotService()->compare($snapshot, $source);

        self::assertSame('incomplete', $output->status);
        self::assertFalse($output->invoiceBaselineAvailable);
        self::assertSame([], $output->changes);
        self::assertSame('2026-10-08', $output->currentInvoices[0]->cancelledAt);
    }
}
