<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\OrderSnapshot;
use App\Exception\BusinessException;
use App\Service\CommissionCalculator;
use PHPUnit\Framework\TestCase;

final class CommissionCalculatorTest extends TestCase
{
    private function rule(array $overrides = []): array
    {
        return [...['version' => 1, 'percentage' => '80.0000', 'basis' => 'margin_psd', 'psdRegion' => 5, 'subtractFreight' => true, 'applyReferenceDiscount' => false], ...$overrides];
    }

    private function order(array $overrides = []): OrderSnapshot
    {
        return new OrderSnapshot(['NUMPED' => '123', 'CODCLI' => '100', 'VLTOTAL' => '1200', 'VLFRETE' => '25', 'COMMISSION_NUMPR' => '3'], 'Cliente', [[...['CODPROD' => '1', 'DESCRICAO' => 'Produto', 'QT' => '3', 'PVENDA' => '400', 'PTABELA' => '300', 'PERCENTUALDESC' => '10', 'COMMISSION_PRICES' => ['5' => ['PVENDA1' => '100', 'PVENDA3' => '350']]], ...$overrides]]);
    }

    public function testPsdUsesRegionAndPlanWithFreightBeforePercentage(): void
    {
        $result = (new CommissionCalculator())->calculate([$this->order()], $this->rule());
        self::assertSame('100.00', $result['grossAmount']);
        self::assertSame('125.000000000000', $result['baseAmount']);
        self::assertSame('350.000000', $result['items'][0]['unitReference']);
    }

    public function testReferenceModesFreightAndDiscountAreExplicit(): void
    {
        $calculator = new CommissionCalculator();
        self::assertSame('220.00', $calculator->calculate([$this->order()], $this->rule(['basis' => 'margin_table']))['grossAmount']);
        self::assertSame('940.00', $calculator->calculate([$this->order()], $this->rule(['basis' => 'sales']))['grossAmount']);
        self::assertSame('120.00', $calculator->calculate([$this->order()], $this->rule(['subtractFreight' => false]))['grossAmount']);
        self::assertSame('184.00', $calculator->calculate([$this->order()], $this->rule(['applyReferenceDiscount' => true]))['grossAmount']);
    }

    public function testNegativeItemMarginsAreIncludedAndRoundingOccursAtEnd(): void
    {
        $positive = $this->order(['QT' => '1', 'PVENDA' => '350.01875']);
        $negative = $this->order(['QT' => '1', 'PVENDA' => '349.99375']);
        $result = (new CommissionCalculator())->calculate([$positive, $negative], $this->rule(['subtractFreight' => false]));
        self::assertSame('0.01', $result['grossAmount']);
    }

    public function testMissingPsdCannotFallBackToItemTable(): void
    {
        $this->expectException(BusinessException::class);
        (new CommissionCalculator())->calculate([$this->order(['COMMISSION_PRICES' => []])], $this->rule());
    }

    public function testNonPositiveMarginCannotProduceCommission(): void
    {
        $this->expectException(BusinessException::class);
        (new CommissionCalculator())->calculate([$this->order(['PVENDA' => '340'])], $this->rule());
    }

    public function testDifferentBranchesAndTablesUseTheirOwnReferences(): void
    {
        $first = new OrderSnapshot(['NUMPED' => '1', 'CODCLI' => '10', 'COMMISSION_PRINCIPAL' => '100', 'CODFILIAL' => '1', 'NUMREGIAO' => 2, 'VLTOTAL' => '400', 'COMMISSION_NUMPR' => '3'], 'Principal', [['CODPROD' => '1', 'DESCRICAO' => 'Produto', 'QT' => '1', 'PVENDA' => '400', 'PTABELA' => '300', 'COMMISSION_PRICES' => ['1' => ['PVENDA3' => '350'], '2' => ['PVENDA3' => '390']]]]);
        $second = new OrderSnapshot(['NUMPED' => '2', 'CODCLI' => '20', 'COMMISSION_PRINCIPAL' => '100', 'CODFILIAL' => '2', 'NUMREGIAO' => 33, 'VLTOTAL' => '500', 'COMMISSION_NUMPR' => '1'], 'Principal', [['CODPROD' => '2', 'DESCRICAO' => 'Produto', 'QT' => '1', 'PVENDA' => '500', 'PTABELA' => '300', 'COMMISSION_PRICES' => ['31' => ['PVENDA1' => '400'], '33' => ['PVENDA1' => '450']]]]);
        $rule = $this->rule(['priceContexts' => [['branch' => '1', 'orderRegion' => 2, 'psdRegion' => 1, 'pscfRegion' => 2], ['branch' => '2', 'orderRegion' => 33, 'psdRegion' => 31, 'pscfRegion' => 33]]]);
        $result = (new CommissionCalculator())->calculate([$first, $second], $rule);
        self::assertSame('100', $first->getCustomerCode());
        self::assertSame('100', $second->getCustomerCode());
        self::assertSame('120.00', $result['grossAmount']);
        self::assertSame('350', $result['items'][0]['unitPsd']);
        self::assertSame('450', $result['items'][1]['unitPscf']);
        self::assertSame('40.00', $result['orders'][0]['grossAmount']);
        self::assertSame('80.00', $result['orders'][1]['grossAmount']);
    }

    public function testComboKeepsEqualPricedComponentsAndMultipliesByComboQuantity(): void
    {
        $components = [];
        foreach (['20', '21'] as $product) {
            $components[] = ['COMPOSITION_BRANCH' => '1', 'COMPOSITION_REGION' => 5, 'CODPRECOCESTA' => '10', 'CODPRODMP' => $product, 'QTMP' => '2', 'PSD_UNIT' => '50', 'COMMISSION_COMPONENT_PRICES' => ['6' => '60']];
        }
        $order = new OrderSnapshot(['NUMPED' => '1', 'CODCLI' => '100', 'CODFILIAL' => '1', 'NUMREGIAO' => 6, 'VLTOTAL' => '900', 'VLFRETE' => '20', 'COMMISSION_NUMPR' => '3'], 'Principal', [['CODPROD' => '1', 'DESCRICAO' => 'Combo promocional', 'QT' => '3', 'PVENDA' => '300', 'PTABELA' => '210', 'COMMISSION_COMPOSITION' => $components]]);
        $rule = $this->rule(['priceContexts' => [['branch' => '*', 'orderRegion' => 6, 'psdRegion' => 5, 'pscfRegion' => 6]]]);
        $result = (new CommissionCalculator())->calculate([$order], $rule);
        self::assertSame('224.00', $result['grossAmount']);
        self::assertCount(2, $result['items'][0]['combo']['components']);
        self::assertSame('200.000000000000', $result['items'][0]['unitPsd']);
        self::assertSame('240.000000000000', $result['items'][0]['unitPscf']);
        self::assertSame('200.00', (new CommissionCalculator())->calculate([$order], $rule, 'atg')['grossAmount']);
    }

    public function testPsdComboRequiresValidatedComposition(): void
    {
        $this->expectException(BusinessException::class);
        (new CommissionCalculator())->calculate([$this->order(['DESCRICAO' => 'Combo promocional'])], $this->rule());
    }
}
