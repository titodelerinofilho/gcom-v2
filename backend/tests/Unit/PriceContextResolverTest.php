<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\BusinessException;
use App\Service\PriceContextResolver;
use PHPUnit\Framework\TestCase;

final class PriceContextResolverTest extends TestCase
{
    public function testBranchOverridesWildcardAndPernambucoHasItsOwnPair(): void
    {
        $rule = ['priceContexts' => [
            ['branch' => '*', 'orderRegion' => 33, 'psdRegion' => 31, 'pscfRegion' => 33],
            ['branch' => '9', 'orderRegion' => 33, 'psdRegion' => 30, 'pscfRegion' => 32],
        ]];
        $resolver = new PriceContextResolver();
        self::assertSame(31, $resolver->resolve(['CODFILIAL' => '1', 'NUMREGIAO' => '33'], $rule)['psdRegion']);
        self::assertSame(30, $resolver->resolve(['CODFILIAL' => '9', 'NUMREGIAO' => '33'], $rule)['psdRegion']);
    }

    public function testMissingContextCannotUseAnUnrelatedRegion(): void
    {
        $this->expectException(BusinessException::class);
        (new PriceContextResolver())->resolve(['CODFILIAL' => '1', 'NUMREGIAO' => 99], ['priceContexts' => []]);
    }
}
