<?php

declare(strict_types=1);

namespace App\Repository\Audit;

use App\Entity\Audit\AuditEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AuditEvent> */
class AuditEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditEvent::class);
    }

    /** @return array{items: list<AuditEvent>, total: int} */
    public function findPage(?string $action, ?string $subject, int $page): array
    {
        $criteria = [];

        if (null !== $action) {
            $criteria['action'] = $action;
        }

        if (null !== $subject) {
            $criteria['subject'] = $subject;
        }

        return ['items' => $this->findBy($criteria, ['id' => 'DESC'], 30, ($page - 1) * 30), 'total' => $this->count($criteria)];
    }

    public function store(AuditEvent $event): void
    {
        $this->getEntityManager()->persist($event);
    }

    public function savePending(): void
    {
        $this->getEntityManager()->flush();
    }
}
