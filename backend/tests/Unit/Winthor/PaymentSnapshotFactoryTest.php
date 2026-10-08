<?php

declare(strict_types=1);

namespace App\Tests\Unit\Winthor;

use App\Exception\Business\BusinessException;
use App\Integration\Winthor\PaymentSnapshotFactory;
use PHPUnit\Framework\TestCase;

final class PaymentSnapshotFactoryTest extends TestCase
{
    public function testRecnumIdentifiesOnePclancRow(): void
    {
        $row = ['NUMTRANS' => '999', 'RECNUM' => '1001', 'VALOR' => '50.00'];
        $snapshot = (new PaymentSnapshotFactory())->create('1001', [$row]);

        self::assertSame('1001', $snapshot['recnum']);
        self::assertSame([$row], $snapshot['records']);
    }

    public function testMissingPrimaryKeyIsRejected(): void
    {
        $this->expectException(BusinessException::class);
        (new PaymentSnapshotFactory())->create('1001', [['NUMTRANS' => '999']]);
    }

    public function testWrongRecnumIsRejected(): void
    {
        $this->expectException(BusinessException::class);
        (new PaymentSnapshotFactory())->create('1001', [['NUMTRANS' => '999', 'RECNUM' => '1002']]);
    }

    public function testMultipleRowsForOneRecnumAreRejected(): void
    {
        $this->expectException(BusinessException::class);
        (new PaymentSnapshotFactory())->create('1001', [
            ['RECNUM' => '1001'],
            ['RECNUM' => '1001'],
        ]);
    }
}
