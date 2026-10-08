<?php

declare(strict_types=1);

namespace App\Integration\Winthor;

interface OrderGatewayInterface
{
    public function search(string $customer, string $from, string $to, ?int $square, ?string $mode = null): array;

    public function assertCommissionEligible(array $numbers, string $customer, string $mode, int $square): void;

    public function assertEligible(array $numbers): void;

    /** @return array{header: array, customerName: string, items: array} */
    public function fetch(string $orderNumber): array;

    /** @return array{header: ?array, items: list<array>, invoices: list<array>} */
    public function inspect(string $orderNumber): array;
}
