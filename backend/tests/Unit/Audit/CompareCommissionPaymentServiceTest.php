<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit;

use App\Entity\Commission\Commission;
use App\Entity\Commission\PaymentLink;
use App\Entity\User\User;
use App\Service\Audit\CompareCommissionPaymentService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CompareCommissionPaymentServiceTest extends TestCase
{
    private function payment(string $amount = '100.00', ?string $reference = '74901'): PaymentLink
    {
        return new PaymentLink((new Commission())->setNetAmount('100.00'), '749', $reference, $amount, new DateTimeImmutable('today'), new User(), '');
    }

    public function testMatchingAmountsDoNotCreateAnAlert(): void
    {
        $output = (new CompareCommissionPaymentService())->compare($this->payment(), ['records' => [['VALOR' => '100.000000', 'VPAGO' => '100,00', 'DTPAGTO' => '2026-10-08']]], 'current', null);

        self::assertSame([], $output->differences);
        self::assertSame('100.00', $output->paidAmount);
    }

    public function testConfirmedAndWinthorPaidAmountsAreComparedWithTheCommission(): void
    {
        $output = (new CompareCommissionPaymentService())->compare($this->payment('90.00'), ['records' => [['VALOR' => '110', 'VPAGO' => '80', 'DTPAGTO' => '2026-10-08']]], 'current', null);

        self::assertCount(3, $output->differences);
        self::assertSame(['-10.00', '10.00', '-20.00'], array_map(static fn ($difference): string => $difference->difference, $output->differences));
        self::assertSame('100.00', $output->commissionAmount);
    }

    public function testZeroWithoutAPaymentDateDoesNotPretendTheWinthorPaymentIsConfirmed(): void
    {
        $output = (new CompareCommissionPaymentService())->compare($this->payment(), ['records' => [['VALOR' => '100', 'VPAGO' => '0', 'DTPAGTO' => null]]], 'current', null);

        self::assertNull($output->paidAmount);
        self::assertSame([], $output->differences);
    }

    public function testMissingWinthorDataStillDetectsAManualAmountDifference(): void
    {
        $output = (new CompareCommissionPaymentService())->compare($this->payment('90.00'), null, 'unavailable', 'Consulta indisponível');

        self::assertCount(1, $output->differences);
        self::assertNull($output->launchAmount);
        self::assertSame('Consulta indisponível', $output->notice);
    }

    public function testNoReferenceDoesNotRunTheRoutine749Comparison(): void
    {
        self::assertNull((new CompareCommissionPaymentService())->compare($this->payment('90.00', null), null, 'unavailable', null));
    }
}
