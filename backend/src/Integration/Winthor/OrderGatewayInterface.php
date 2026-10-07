<?php

declare(strict_types=1);

namespace App\Integration\Winthor;

interface OrderGatewayInterface
{
    public function search(string $customer, string $from, string $to, ?int $square): array;

    public function assertEligible(array $numbers): void;

    /** @return array{header: array, customerName: string, items: array} */
    public function fetch(string $orderNumber): array;
}
