<?php

declare(strict_types=1);

namespace App\Integration\Winthor;

interface MovementGatewayInterface
{
    public function cancellations(string $customer, string $from, string $to): array;

    public function overdue(string $customer): array;

    public function returns(string $customer, bool $atg, ?string $transaction = null): array;

    public function cancelledOrder(string $number): array;
}
