<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Entity\Order\OrderSnapshot;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\OrderGatewayInterface;

final readonly class ValidateCommissionOrdersService
{
    public function __construct(private CommissionSquareService $squares, private OrderGatewayInterface $gateway)
    {
    }

    /** @param list<OrderSnapshot> $orders */
    public function validate(array $orders, string $mode, int $square): void
    {
        $this->squares->validate($square, $mode);
        $customer = $orders[0]->getCustomerCode();

        foreach ($orders as $order) {
            if ($customer !== $order->getCustomerCode() || $square !== (int) ($order->getHeader()['CODPRACA'] ?? 0)) {
                throw new BusinessException('Os pedidos devem pertencer ao mesmo cliente principal e à praça selecionada.', 409);
            }
        }

        $this->gateway->assertCommissionEligible(array_map(static fn (OrderSnapshot $order): string => $order->getOrderNumber(), $orders), $customer, $mode, $square);
    }
}
