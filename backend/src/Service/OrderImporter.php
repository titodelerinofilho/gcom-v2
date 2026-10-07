<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\OrderSnapshot;
use App\Entity\User;
use App\Integration\Winthor\OrderGatewayInterface;
use App\Repository\OrderSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;

final class OrderImporter
{
    public function __construct(
        private OrderGatewayInterface $winthor,
        private OrderSnapshotRepository $orders,
        private EntityManagerInterface $em,
        private AuditRecorder $audit,
    ) {
    }

    public function import(string $number, User $actor): OrderSnapshot
    {
        if ($existing = $this->orders->findOneBy(['orderNumber' => $number])) {
            return $existing;
        }
        $data = $this->winthor->fetch($number);

        return $this->em->wrapInTransaction(function () use ($data, $actor) {
            $order = new OrderSnapshot($data['header'], $data['customerName'], $data['items']);
            $this->em->persist($order);
            $this->audit->record($actor, 'order.imported', 'order:'.$order->getOrderNumber(), ['items' => count($data['items'])]);

            return $order;
        });
    }
}
