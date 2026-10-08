<?php

declare(strict_types=1);

namespace App\Tests\Double\Winthor;

use App\Integration\Winthor\MovementGatewayInterface;

final class InMemoryMovementGateway implements MovementGatewayInterface
{
    public static array $cancelled = [];

    public static array $returnRows = [];

    public static array $overdueRows = [];

    public function cancellations(string $customer, string $from, string $to): array
    {
        return array_merge(...array_values(self::$cancelled));
    }

    public function overdue(string $customer): array
    {
        return self::$overdueRows;
    }

    public function returns(string $customer, bool $atg, ?string $transaction = null): array
    {
        return array_values(array_filter(self::$returnRows, static fn (array $row): bool => null === $transaction || false === isset($row['NUMTRANSENT']) || (string) $row['NUMTRANSENT'] === $transaction));
    }

    public function cancelledOrder(string $number): array
    {
        return self::$cancelled[$number] ?? [];
    }
}
