<?php

declare(strict_types=1);

namespace App\Repository\Adjustment;

use App\Entity\Adjustment\Adjustment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Adjustment> */
final class AdjustmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Adjustment::class);
    }

    /** @return list<Adjustment> */
    public function findPage(?string $customer, bool $available, int $page): array
    {
        return $this->findBy($this->criteria($customer, $available), ['id' => 'DESC'], 30, ($page - 1) * 30);
    }

    public function countMatching(?string $customer, bool $available): int
    {
        return $this->count($this->criteria($customer, $available));
    }

    public function lockCustomer(string $customerCode): void
    {
        $sql = 'SELECT pg_advisory_xact_lock(hashtextextended(:source, 0))';
        $this->getEntityManager()->getConnection()->executeQuery($sql, ['source' => 'gcom:customer:'.$customerCode]);
    }

    /** @return array{customerCode?: string, commission?: null} */
    private function criteria(?string $customer, bool $available): array
    {
        $criteria = [];

        if (null !== $customer) {
            $criteria['customerCode'] = $customer;
        }

        if (true === $available) {
            $criteria['commission'] = null;
        }

        return $criteria;
    }

    /** @return list<int> */
    public function pendingIds(string $customer, ?array $returnTransactions = null): array
    {
        $pending = $this->findBy(['customerCode' => $customer, 'commission' => null], ['id' => 'ASC']);
        $ids = [];

        foreach ($pending as $adjustment) {
            $isUnselectedReturn = null !== $returnTransactions
                && 'return' === $adjustment->getType()
                && null !== $adjustment->getSourceKey()
                && false === in_array(str_replace('winthor:return:', '', $adjustment->getSourceKey()), $returnTransactions, true);

            if (true === $isUnselectedReturn) {
                continue;
            }

            $ids[] = $adjustment->getId();
        }

        return $ids;
    }

    /** @return list<Adjustment> */
    public function findForCommission(\App\Entity\Commission\Commission $commission): array
    {
        return $this->findBy(['commission' => $commission]);
    }

    public function lockSource(string $source): void
    {
        $sql = 'SELECT pg_advisory_xact_lock(hashtextextended(:source, 0))';
        $this->getEntityManager()->getConnection()->executeQuery($sql, ['source' => $source]);
    }

    public function findBySource(string $source): ?Adjustment
    {
        return $this->findOneBy(['sourceKey' => $source]);
    }

    /** @return list<Adjustment> */
    public function findReturnsForCustomer(string $customer): array
    {
        return $this->findBy(['customerCode' => $customer, 'type' => 'return']);
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

    public function store(Adjustment $entity): void
    {
        $this->getEntityManager()->persist($entity);
    }

    public function findLocked(int $id): ?Adjustment
    {
        return $this->find($id, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
    }
}
