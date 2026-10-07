<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Integration\Winthor\OrderGatewayInterface;

final class InMemoryOrderGateway implements OrderGatewayInterface
{
    public static array $searchCalls = [];

    public function search(string $customer, string $from, string $to, ?int $square): array
    {
        self::$searchCalls[] = compact('customer', 'from', 'to', 'square');
        $order = $this->fetch('123');

        return [array_replace($order['header'], ['DATA' => $from, 'CLIENTE' => $order['customerName'], 'CODPLPAG' => '1'])];
    }

    public function assertEligible(array $numbers): void
    {
        foreach ($numbers as $number) {
            if (InMemoryMovementGateway::$cancelled[$number] ?? []) {
                throw new \App\Exception\BusinessException('Pedido cancelado.', 409);
            }
        }
    }

    public function fetch(string $orderNumber): array
    {
        return ['header' => ['NUMPED' => $orderNumber, 'CODCLI' => '100', 'VLTOTAL' => '1200.00', 'POSICAO' => 'F', 'CODFILIAL' => '1', 'NUMREGIAO' => 2, 'VLFRETE' => '25.00', 'COMMISSION_NUMPR' => '3'],
            'customerName' => 'Cliente de Teste',
            'items' => [['CODPROD' => '200', 'DESCRICAO' => 'Produto de Teste', 'QT' => '3.000000', 'PVENDA' => '222' === $orderNumber ? '400.125000' : '400.000000', 'PTABELA' => '300.000000', 'NUMSEQ' => '1', 'COMMISSION_PRICES' => ['1' => ['PVENDA3' => '350.000000'], '2' => ['PVENDA3' => '400.000000']]]]];
    }
}
