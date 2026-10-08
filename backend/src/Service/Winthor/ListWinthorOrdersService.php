<?php

declare(strict_types=1);

namespace App\Service\Winthor;

use App\Dto\Winthor\Input\ListWinthorOrdersInput;
use App\Dto\Winthor\Output\WinthorRowOutput;
use App\Dto\Winthor\Output\WinthorRowsOutput;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\OrderGatewayInterface;
use DateTimeImmutable;

final readonly class ListWinthorOrdersService
{
    public function __construct(
        private OrderGatewayInterface $gateway,
    ) {
    }

    public function list(ListWinthorOrdersInput $input): WinthorRowsOutput
    {
        $from = new DateTimeImmutable($input->from);
        $to = new DateTimeImmutable($input->to);

        if ($from > $to || 366 < $from->diff($to)->days) {
            throw new BusinessException('Informe um período válido de até 366 dias.');
        }

        $rows = $this->gateway->search($input->customer, $input->from, $input->to, $input->square);
        $items = array_map(static fn (array $row): WinthorRowOutput => new WinthorRowOutput($row), $rows);

        return new WinthorRowsOutput($items);
    }
}
