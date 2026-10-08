<?php

declare(strict_types=1);

namespace App\Service\Order;

use App\Dto\Order\Output\OrderOutput;
use App\Exception\Business\BusinessException;
use App\Repository\Order\OrderSnapshotRepository;

final readonly class GetOrderService
{
    public function __construct(
        private OrderSnapshotRepository $repository,
    ) {
    }

    public function get(int $id): OrderOutput
    {
        $order = $this->repository->find($id) ?? throw new BusinessException('Pedido não encontrado.', 404);

        return new OrderOutput($order, true);
    }
}
