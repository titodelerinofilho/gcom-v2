<?php

declare(strict_types=1);

namespace App\Service\Order;

use App\Dto\Order\Input\ListOrdersInput;
use App\Dto\Order\Output\ListOrdersOutput;
use App\Dto\Order\Output\OrderOutput;
use App\Entity\Order\OrderSnapshot;
use App\Repository\Order\OrderSnapshotRepository;

final readonly class ListOrdersService
{
    public function __construct(
        private OrderSnapshotRepository $repository,
    ) {
    }

    public function list(ListOrdersInput $input): ListOrdersOutput
    {
        $page = $this->repository->findPage($input->customer, $input->available, $input->page);
        $items = array_map(static fn (OrderSnapshot $item): OrderOutput => new OrderOutput($item), $page['items']);

        return new ListOrdersOutput($items, $page['total'], $input->page);
    }
}
