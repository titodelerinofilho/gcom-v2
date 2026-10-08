<?php

declare(strict_types=1);

namespace App\Repository\Order;

use App\Entity\Order\OrderSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OrderSnapshot> */
class OrderSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderSnapshot::class);
    }

    /** @return array{items: list<OrderSnapshot>, total: int} */
    public function findPage(?string $customer, bool $available, int $page): array
    {
        $criteria = [];

        if (null !== $customer) {
            $criteria['customerCode'] = $customer;
        }

        if (true === $available) {
            $criteria['commission'] = null;
        }

        return ['items' => $this->findBy($criteria, ['id' => 'DESC'], 30, ($page - 1) * 30), 'total' => $this->count($criteria)];
    }

    /** @return list<OrderSnapshot> */
    public function findCommissionedForCustomer(string $customer): array
    {
        return $this->createQueryBuilder('orderSnapshot')
            ->where('orderSnapshot.customerCode = :customer')
            ->andWhere('orderSnapshot.commission IS NOT NULL')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getResult();
    }

    /**
     * @template TResult
     *
     * @param callable(): TResult $operation
     *
     * @return TResult
     */
    public function save(callable $operation): mixed
    {
        return $this->getEntityManager()->wrapInTransaction($operation);
    }

    public function store(OrderSnapshot $entity): void
    {
        $this->getEntityManager()->persist($entity);
    }

    public function findLocked(int $id): ?OrderSnapshot
    {
        return $this->find($id, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
    }
}
