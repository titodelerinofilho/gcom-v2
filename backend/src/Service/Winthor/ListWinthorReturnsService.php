<?php

declare(strict_types=1);

namespace App\Service\Winthor;

use App\Dto\Winthor\Input\ListWinthorReturnsInput;
use App\Dto\Winthor\Output\WinthorRowOutput;
use App\Dto\Winthor\Output\WinthorRowsOutput;
use App\Integration\Winthor\MovementGatewayInterface;

final readonly class ListWinthorReturnsService
{
    public function __construct(
        private MovementGatewayInterface $gateway,
    ) {
    }

    public function list(ListWinthorReturnsInput $input): WinthorRowsOutput
    {
        $rows = $this->gateway->returns($input->customer, $input->atg);

        $items = array_map(static fn (array $row): WinthorRowOutput => new WinthorRowOutput($row), $rows);

        return new WinthorRowsOutput($items);
    }
}
