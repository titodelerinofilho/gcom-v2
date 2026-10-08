<?php

declare(strict_types=1);

namespace App\Repository\CommissionRule;

use App\Entity\CommissionRule\CommissionRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CommissionRule> */
final class CommissionRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommissionRule::class);
    }

    public function findCurrent(): ?CommissionRule
    {
        return $this->findOneBy([], ['id' => 'DESC']);
    }

    /** @return list<CommissionRule> */
    public function findPage(int $page): array
    {
        return $this->findBy([], ['id' => 'DESC'], 30, ($page - 1) * 30);
    }

    public function lockVersion(): void
    {
        $sql = 'SELECT pg_advisory_xact_lock(749080)';
        $this->getEntityManager()->getConnection()->executeQuery($sql);
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

    public function store(CommissionRule $entity): void
    {
        $this->getEntityManager()->persist($entity);

        $this->getEntityManager()->flush();
    }
}
