<?php

declare(strict_types=1);

namespace App\Tests\Unit\Finance;

use App\Exception\Business\BusinessException;
use App\Service\Finance\MoneyService;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testDecimalArithmeticDoesNotLoseCents(): void
    {
        self::assertSame(['gross' => '0.30', 'deductions' => '0.20', 'net' => '0.10'], MoneyService::net('0.30', ['0.10', '0.10']));
    }

    public function testDeductionsCannotExceedCommission(): void
    {
        $this->expectException(BusinessException::class);
        MoneyService::net('100', ['100']);
    }

    public function testBrazilianThousandsSeparatorsAreRejected(): void
    {
        $this->expectException(BusinessException::class);
        MoneyService::positive('1.200,50');
    }

    public function testZeroAndNegativeAmountsAreRejected(): void
    {
        $this->expectException(BusinessException::class);
        MoneyService::positive('0');
    }

    public function testRoundingIsExplicit(): void
    {
        self::assertSame('1.01', MoneyService::normalize('1.005'));
    }
}
