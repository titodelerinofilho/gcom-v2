<?php

declare(strict_types=1);

namespace App\Repository\Commission;

use App\Entity\Commission\PaymentLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PaymentLink> */
class PaymentLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentLink::class);
    }

    public function findForCommission(\App\Entity\Commission\Commission $commission): ?PaymentLink
    {
        return $this->findOneBy(['commission' => $commission]);
    }

    /** @param list<string> $orderNumbers
     * @return list<PaymentLink>
     */
    public function findPaidForOrders(string $customer, array $orderNumbers): array
    {
        if ([] === $orderNumbers) {
            return [];
        }

        return $this->createQueryBuilder('payment')
            ->addSelect('commission', 'orders', 'items')
            ->join('payment.commission', 'commission')
            ->join('commission.orders', 'matchingOrders')
            ->join('commission.orders', 'orders')
            ->join('orders.items', 'items')
            ->where('commission.customerCode = :customer')
            ->andWhere('commission.status = :status')
            ->andWhere('matchingOrders.orderNumber IN (:orders)')
            ->setParameter('customer', $customer)
            ->setParameter('status', 'paid')
            ->setParameter('orders', $orderNumbers)
            ->orderBy('payment.paidAt', 'DESC')
            ->addOrderBy('payment.id', 'DESC')
            ->getQuery()->getResult();
    }

    public function store(PaymentLink $payment): void
    {
        $this->getEntityManager()->persist($payment);
    }
}
