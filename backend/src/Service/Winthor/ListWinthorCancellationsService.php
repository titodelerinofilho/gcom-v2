<?php

declare(strict_types=1);

namespace App\Service\Winthor;

use App\Dto\Winthor\Input\ListWinthorCancellationsInput;
use App\Dto\Winthor\Output\WinthorRowOutput;
use App\Dto\Winthor\Output\WinthorRowsOutput;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\MovementGatewayInterface;
use DateTimeImmutable;

final readonly class ListWinthorCancellationsService
{
    public function __construct(
        private MovementGatewayInterface $gateway,
    ) {
    }

    public function list(ListWinthorCancellationsInput $input): WinthorRowsOutput
    {
        $from = new DateTimeImmutable($input->from);
        $to = new DateTimeImmutable($input->to);

        if ($from > $to || 366 < $from->diff($to)->days) {
            throw new BusinessException('Informe um período válido de até 366 dias.');
        }

        $rows = $this->gateway->cancellations($input->customer, $input->from, $input->to);
        $items = array_map(static fn (array $row): WinthorRowOutput => new WinthorRowOutput($row), $rows);

        return new WinthorRowsOutput($items);
    }
}
