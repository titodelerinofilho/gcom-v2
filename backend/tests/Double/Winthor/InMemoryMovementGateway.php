<?php

declare(strict_types=1);

namespace App\Tests\Double\Winthor;

use App\Integration\Winthor\MovementGatewayInterface;

final class InMemoryMovementGateway implements MovementGatewayInterface
{
    public static array $cancelled = [];

    public static array $returnRows = [];

    public function cancellations(string $customer, string $from, string $to): array
    {
        return array_merge(...array_values(self::$cancelled));
    }

    public function overdue(string $customer): array
    {
        return [];
    }

    public function returns(string $customer, bool $atg, ?string $transaction = null): array
    {
        return self::$returnRows;
    }

    public function cancelledOrder(string $number): array
    {
        return self::$cancelled[$number] ?? [];
    }
}
