<?php

declare(strict_types=1);

namespace App\Repository\Report;

use App\Entity\Report\ReportSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ReportSnapshot> */
class ReportSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportSnapshot::class);
    }

    public function save(ReportSnapshot $snapshot, callable $recordAudit): void
    {
        $this->getEntityManager()->wrapInTransaction(function () use ($snapshot, $recordAudit): void {
            $this->getEntityManager()->persist($snapshot);
            $recordAudit();
        });
    }

    /** @return array{items: list<ReportSnapshot>, total: int} */
    public function findPage(int $page): array
    {
        return ['items' => $this->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC'], 30, ($page - 1) * 30), 'total' => $this->count([])];
    }
}
