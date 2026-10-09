<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reseller;

use App\Dto\Reseller\Output\ActivityOutput;
use App\Dto\Reseller\Output\DebtSummaryOutput;
use App\Service\Reseller\ResellerRatingService;
use PHPUnit\Framework\TestCase;

final class ResellerRatingTest extends TestCase
{
    public function testStrongCleanPortfolioGetsGoldAndScoresAddUp(): void
    {
        $rating = new ResellerRatingService()->rate(new ActivityOutput(60, '100000', 10, 10, 0, '0', '0'), new DebtSummaryOutput(0, '0', 0, '0', '0', '0', 0), 365);
        self::assertSame('gold', $rating->tier);
        self::assertSame(100, $rating->score);
        self::assertSame($rating->score, array_sum(array_map(static fn ($component): int => $component->points, $rating->components)));
    }

    public function testSevereDelinquencyCannotGetGoldEvenWithHighSales(): void
    {
        $rating = new ResellerRatingService()->rate(new ActivityOutput(600, '100000', 10, 10, 0, '0', '0'), new DebtSummaryOutput(1, '1', 1, '1', '0', '1', 60), 365);
        self::assertSame('bronze', $rating->tier);
    }

    public function testReturnsAndCancellationsReduceRatingAndSmallSamplesAreLimited(): void
    {
        $service = new ResellerRatingService();
        $clean = new DebtSummaryOutput(0, '0', 0, '0', '0', '0', 0);
        $rating = $service->rate(new ActivityOutput(60, '100000', 10, 10, 60, '100000', '20000'), $clean, 365);
        self::assertSame('silver', $rating->tier);
        self::assertSame(70, $rating->score);
        $small = $service->rate(new ActivityOutput(1, '100000', 1, 1, 0, '0', '0'), $clean, 365);
        self::assertSame('bronze', $small->tier);
        self::assertSame('Histórico reduzido', $small->confidence);
    }

    public function testNoSalesDoesNotProduceAFalseGoldOrDivideByZero(): void
    {
        $rating = new ResellerRatingService()->rate(new ActivityOutput(0, '0', 0, 0, 0, '0', '1'), new DebtSummaryOutput(1, '100', 1, '100', '100', '0', 1), 365);
        self::assertSame('unrated', $rating->tier);
        self::assertSame(0, $rating->score);
        self::assertSame(0, array_sum(array_map(static fn ($component): int => $component->points, $rating->components)));
    }
}
