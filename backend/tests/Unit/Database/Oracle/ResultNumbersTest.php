<?php

declare(strict_types=1);

namespace App\Tests\Unit\Database\Oracle;

use App\Database\Statement\Oracle\NumberNormalizer;
use App\Database\Statement\Result;
use App\Exception\DatabaseException;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class ResultNumbersTest extends TestCase
{
    public function testExactDecimalsFractionsAndScientificNotation(): void
    {
        foreach (['14251,75' => '14251.75', ',5' => '0.5', '-,5' => '-0.5', '.003' => '0.003', '1,23E-4' => '0.000123', '123456789012345678,123456' => '123456789012345678.123456', '411.6' => '411.6', '0' => '0'] as $source => $expected) {
            self::assertSame($expected, NumberNormalizer::normalize((string) $source));
        }
    }

    public function testOnlyOracleNumberColumnsAreConvertedAndNullRemainsNull(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('columnCount')->willReturn(3);
        $statement->method('getColumnMeta')->willReturnMap([
            [0, ['name' => 'AMOUNT', 'native_type' => 'NUMBER']],
            [1, ['name' => 'TEXT', 'native_type' => 'VARCHAR2']],
            [2, ['name' => 'EMPTY', 'native_type' => 'NUMBER']],
        ]);
        $statement->method('fetch')->willReturn(['AMOUNT' => '14251,75', 'TEXT' => '1,5', 'EMPTY' => null]);
        $statement->method('fetchAll')->willReturn([['AMOUNT' => ',5', 'TEXT' => '1,5', 'EMPTY' => null]]);
        $statement->method('fetchColumn')->willReturn('1,23E-4');

        $result = new Result($statement, normalizeOracleNumbers: true);
        self::assertSame(['AMOUNT' => '14251.75', 'TEXT' => '1,5', 'EMPTY' => null], $result->fetchAssociative());
        self::assertSame([['AMOUNT' => '0.5', 'TEXT' => '1,5', 'EMPTY' => null]], $result->fetchAllAssociative());
        self::assertSame('0.000123', $result->fetchOne());
    }

    public function testNonOracleResultsKeepTheirOriginalFormat(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::never())->method('getColumnMeta');
        $statement->method('fetchColumn')->willReturn('1,5');

        self::assertSame('1,5', (new Result($statement))->fetchOne());
    }

    public function testAmbiguousThousandsSeparatorsAreNotSilentlyConverted(): void
    {
        $this->expectException(DatabaseException::class);
        NumberNormalizer::normalize('1.234,56');
    }
}
