<?php

declare(strict_types=1);

namespace App\Tests\Unit\Commission;

use App\Service\Commission\CommissionItemAllocationService;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class CommissionItemAllocationTest extends TestCase
{
    private function calculation(array $items, string $freight, string $gross, string $percentage = '80'): array
    {
        return ['rule' => ['percentage' => $percentage], 'grossAmount' => $gross, 'orders' => [['orderNumber' => '1', 'deductedFreight' => $freight, 'grossAmount' => $gross]], 'items' => array_map(static fn (array $item): array => ['orderNumber' => '1', ...$item], $items)];
    }

    public function testHistoricalProductsMatchTheSavedTotalWithoutChangingTheSnapshot(): void
    {
        $snapshot = $this->calculation([['sales' => '1146.90', 'reference' => '902.90'], ['sales' => '243.80', 'reference' => '172.98'], ['sales' => '3295.80', 'reference' => '2395.80']], '0', '971.86');
        $original = $snapshot;
        $result = (new CommissionItemAllocationService())->allocate($snapshot);

        self::assertSame(['195.20', '56.66', '720.00'], array_column($result['items'], 'commissionAmount'));
        self::assertSame($original, $snapshot);
        self::assertSame('971.86', $result['grossAmount']);
        self::assertSame($result, (new CommissionItemAllocationService())->allocate($result));
    }

    public function testFreightIsDistributedBySalesAndProductCommissionsReconcile(): void
    {
        $result = (new CommissionItemAllocationService())->allocate($this->calculation([['sales' => '100', 'reference' => '50'], ['sales' => '300', 'reference' => '100']], '40', '168.00'));

        self::assertTrue(BigDecimal::of($result['items'][0]['allocatedFreight'])->isEqualTo('10'));
        self::assertTrue(BigDecimal::of($result['items'][1]['allocatedFreight'])->isEqualTo('30'));
        self::assertSame(['32.00', '136.00'], array_column($result['items'], 'commissionAmount'));
    }

    public function testResidualCentIsAssignedDeterministically(): void
    {
        $result = (new CommissionItemAllocationService())->allocate($this->calculation([['sales' => '1', 'reference' => '0.994'], ['sales' => '1', 'reference' => '0.994']], '0', '0.01', '100'));

        self::assertSame(['0.00', '0.01'], array_column($result['items'], 'commissionAmount'));
        self::assertSame(['-0.01', '0.00'], array_column($result['items'], 'roundingAdjustment'));
    }

    public function testNegativeProductCommissionIsPreserved(): void
    {
        $result = (new CommissionItemAllocationService())->allocate($this->calculation([['sales' => '100', 'reference' => '50'], ['sales' => '100', 'reference' => '110']], '0', '32.00'));

        self::assertSame(['40.00', '-8.00'], array_column($result['items'], 'commissionAmount'));
    }

    public function testOrderRoundingAlsoReconcilesWithTheCommissionTotal(): void
    {
        $snapshot = ['percentageApplied' => '100', 'grossAmount' => '0.01', 'orders' => [['orderNumber' => '1', 'deductedFreight' => '0', 'grossAmount' => '0.00'], ['orderNumber' => '2', 'deductedFreight' => '0', 'grossAmount' => '0.01']], 'items' => [['orderNumber' => '1', 'sales' => '1', 'reference' => '0.996'], ['orderNumber' => '2', 'sales' => '1', 'reference' => '0.996']]];
        $result = (new CommissionItemAllocationService())->allocate($snapshot);

        self::assertSame(['0.00', '0.01'], array_column($result['items'], 'commissionAmount'));
    }

    public function testInsufficientOrInconsistentHistoricalDataIsNotInvented(): void
    {
        $allocator = new CommissionItemAllocationService();
        self::assertSame([], $allocator->allocate([]));
        $snapshot = $this->calculation([['sales' => '100', 'reference' => '50']], '0', '99.00');
        self::assertSame($snapshot, $allocator->allocate($snapshot));
    }
}
