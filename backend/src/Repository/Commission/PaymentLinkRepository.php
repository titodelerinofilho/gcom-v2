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

    public function store(PaymentLink $payment): void
    {
        $this->getEntityManager()->persist($payment);
    }
}
