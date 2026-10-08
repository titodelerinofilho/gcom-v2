<?php

declare(strict_types=1);

namespace App\Tests\Double\Winthor;

use App\Integration\Winthor\OrderGatewayInterface;

final class InMemoryOrderGateway implements OrderGatewayInterface
{
    public static array $searchCalls = [];

    public static array $headers = [];

    public static array $inspections = [];

    public function inspect(string $orderNumber): array
    {
        $source = $this->fetch($orderNumber);

        return self::$inspections[$orderNumber] ?? ['header' => $source['header'], 'items' => $source['items'], 'invoices' => $source['header']['COMMISSION_INVOICES'] ?? []];
    }

    public function search(string $customer, string $from, string $to, ?int $square, ?string $mode = null): array
    {
        self::$searchCalls[] = compact('customer', 'from', 'to', 'square', 'mode');
        $order = $this->fetch('123');

        if (null !== $square && $square !== (int) $order['header']['CODPRACA']) {
            return [];
        }

        return [array_replace($order['header'], ['DATA' => $from, 'CLIENTE' => $order['customerName'], 'CODPLPAG' => '1'])];
    }

    public function assertCommissionEligible(array $numbers, string $customer, string $mode, int $square): void
    {
        $this->assertEligible($numbers);
        foreach ($numbers as $number) {
            $header = $this->fetch($number)['header'];
            $principal = 'normal' === $mode ? ($header['COMMISSION_REVENDA'] ?? null) : ($header['COMMISSION_PRINCIPAL'] ?? $header['CODCLI']);

            if ($customer !== $principal || $square !== (int) $header['CODPRACA']) {
                throw new \App\Exception\Business\BusinessException('Pedido fora da praça ou vínculo selecionado.', 409);
            }
        }
    }

    public function assertEligible(array $numbers): void
    {
        foreach ($numbers as $number) {
            if (InMemoryMovementGateway::$cancelled[$number] ?? []) {
                throw new \App\Exception\Business\BusinessException('Pedido cancelado.', 409);
            }
        }
    }

    public function fetch(string $orderNumber): array
    {
        return ['header' => ['CODPRACA' => 562, 'COMMISSION_REVENDA' => '100', 'COMMISSION_PRINCIPAL' => '100', 'NUMPED' => $orderNumber, 'NUMNOTA' => '900', 'CODCLI' => '100', 'VLTOTAL' => '1200.00', 'POSICAO' => 'F', 'CODFILIAL' => '1', 'NUMREGIAO' => 2, 'VLFRETE' => '25.00', 'COMMISSION_NUMPR' => '3', ...(self::$headers[$orderNumber] ?? [])],
            'customerName' => 'Cliente de Teste',
            'items' => [['CODPROD' => '200', 'DESCRICAO' => 'Produto de Teste', 'QT' => '3.000000', 'PVENDA' => '222' === $orderNumber ? '400.125000' : '400.000000', 'PTABELA' => '300.000000', 'NUMSEQ' => '1', 'COMMISSION_PRICES' => ['1' => ['PVENDA3' => '350.000000'], '2' => ['PVENDA3' => '400.000000'], '5' => ['PVENDA3' => '300.000000'], '6' => ['PVENDA3' => '380.000000']]]]];
    }
}
