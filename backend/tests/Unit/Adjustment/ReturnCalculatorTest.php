<?php

declare(strict_types=1);

namespace App\Tests\Unit\Adjustment;

use App\Service\Adjustment\ReturnCalculatorService;
use PHPUnit\Framework\TestCase;

final class ReturnCalculatorTest extends TestCase
{
    public function testNormalAndAtgUsePtabela1WithTheirOwnPercentages(): void
    {
        $rule = ['priceContexts' => [['branch' => '*', 'orderRegion' => 33, 'psdRegion' => 31, 'pscfRegion' => 33]], 'returnPercentage' => '80', 'atgReturnPercentage' => '100'];
        $rows = [['CODFILIAL' => '2', 'NUMREGIAO' => 33, 'CODPROD' => '10', 'NUMPED' => '123', 'NUMNOTA' => '100', 'QT' => '2', 'PUNIT' => '120', 'COMMISSION_RETURN_PRICES' => ['31' => '100', '33' => '115']]];

        $calculator = new ReturnCalculatorService(new \App\Service\Commission\PriceContextResolverService());

        self::assertSame('32.00', $calculator->calculate($rows, $rule, false)['amount']);
        self::assertSame('40.00', $calculator->calculate($rows, $rule, true)['amount']);
        self::assertSame('200.000000000000', $calculator->calculate($rows, $rule, true)['items'][0]['reference']);
    }
}
