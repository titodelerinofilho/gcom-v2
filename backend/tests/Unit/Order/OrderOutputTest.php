<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order;

use App\Dto\Order\Output\OrderOutput;
use App\Entity\Order\OrderSnapshot;
use PHPUnit\Framework\TestCase;

final class OrderOutputTest extends TestCase
{
    public function testListDistinguishesPrincipalFromOrderAuthorWithoutExposingHeader(): void
    {
        $header = ['NUMPED' => '123', 'NUMNOTA' => '987', 'COMMISSION_PRINCIPAL' => '100', 'CODCLI' => '200', 'COMMISSION_FINAL_CUSTOMER_NAME' => 'Cliente autor', 'VLTOTAL' => '1200'];
        $order = new OrderSnapshot($header, 'Cliente principal', []);
        $output = (new OrderOutput($order))->jsonSerialize();

        self::assertSame('987', $output['invoiceNumber']);
        self::assertSame('100', $output['customerCode']);
        self::assertSame('Cliente principal', $output['customerName']);
        self::assertSame('200', $output['authorCustomerCode']);
        self::assertSame('Cliente autor', $output['authorCustomerName']);
        self::assertArrayNotHasKey('header', $output);
        self::assertSame($header, $order->getHeader());
    }

    public function testDirectCustomersReuseThePreservedCustomerName(): void
    {
        $order = new OrderSnapshot(['NUMPED' => '123', 'CODCLI' => '100', 'VLTOTAL' => '1200'], 'Cliente direto', []);
        $output = new OrderOutput($order);

        self::assertNull($output->invoiceNumber);
        self::assertSame('100', $output->authorCustomerCode);
        self::assertSame('Cliente direto', $output->authorCustomerName);
    }

    public function testFiscalReceiptFallbackUsesPreservedNumber(): void
    {
        $order = new OrderSnapshot(['NUMPED' => '123', 'NUMNOTA' => null, 'NUMCUPOM' => '456', 'CODCLI' => '100', 'VLTOTAL' => '1200'], 'Cliente direto', []);

        self::assertSame('456', (new OrderOutput($order))->invoiceNumber);
    }

    public function testMissingAuthorNameCannotBeReplacedByPrincipalName(): void
    {
        $order = new OrderSnapshot(['NUMPED' => '123', 'COMMISSION_PRINCIPAL' => '100', 'CODCLI' => '200', 'VLTOTAL' => '1200'], 'Cliente principal', []);
        $output = new OrderOutput($order, true);

        self::assertSame('200', $output->authorCustomerCode);
        self::assertNull($output->authorCustomerName);
        self::assertSame('Cliente principal', $output->customerName);
    }
}
