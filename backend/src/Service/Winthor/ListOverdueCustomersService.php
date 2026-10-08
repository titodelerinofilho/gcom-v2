<?php

declare(strict_types=1);

namespace App\Service\Winthor;

use App\Dto\Winthor\Input\ListOverdueCustomersInput;
use App\Dto\Winthor\Output\WinthorRowOutput;
use App\Dto\Winthor\Output\WinthorRowsOutput;
use App\Integration\Winthor\MovementGatewayInterface;

final readonly class ListOverdueCustomersService
{
    public function __construct(
        private MovementGatewayInterface $gateway,
    ) {
    }

    public function list(ListOverdueCustomersInput $input): WinthorRowsOutput
    {
        $rows = $this->gateway->overdue($input->customer);
        $items = array_map(static fn (array $row): WinthorRowOutput => new WinthorRowOutput($row), $rows);

        return new WinthorRowsOutput($items);
    }
}
