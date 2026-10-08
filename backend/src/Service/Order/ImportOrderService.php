<?php

declare(strict_types=1);

namespace App\Service\Order;

use App\Dto\Order\Input\ImportOrderInput;
use App\Dto\Order\Output\OrderOutput;
use App\Entity\Order\OrderSnapshot;
use App\Entity\User\User;
use App\Integration\Winthor\OrderGatewayInterface;
use App\Repository\Order\OrderSnapshotRepository;
use App\Service\Audit\AuditRecorderService;

final class ImportOrderService
{
    public function __construct(
        private OrderGatewayInterface $winthor,
        private OrderSnapshotRepository $orders,
        private AuditRecorderService $audit,
    ) {
    }

    public function import(ImportOrderInput $input, User $actor): OrderOutput
    {
        $number = $input->orderNumber;
        $existing = $this->orders->findOneBy(['orderNumber' => $number]);

        if (null !== $existing) {
            return new OrderOutput($existing, true);
        }

        $data = $this->winthor->fetch($number);

        $order = $this->orders->save(function () use ($data, $actor) {
            $order = new OrderSnapshot($data['header'], $data['customerName'], $data['items']);

            $this->orders->store($order);
            $this->audit->record($actor, 'order.imported', 'order:'.$order->getOrderNumber(), ['items' => count($data['items'])]);

            return $order;
        });

        return new OrderOutput($order, true);
    }
}
