<?php

declare(strict_types=1);

namespace App\Repository\Enterprise;

use App\Entity\Enterprise\Enterprise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Enterprise> */
final class EnterpriseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enterprise::class);
    }

    public function current(): ?Enterprise
    {
        return $this->find(1);
    }

    public function save(callable $operation): mixed
    {
        return $this->getEntityManager()->wrapInTransaction(function () use ($operation) {
            $this->getEntityManager()->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(7349201)');

            return $operation();
        });
    }

    public function store(Enterprise $enterprise): void
    {
        $this->getEntityManager()->persist($enterprise);
        $this->getEntityManager()->flush();
    }
}
