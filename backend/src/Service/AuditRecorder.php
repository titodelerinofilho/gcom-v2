<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditEvent;
use App\Entity\User;
use App\Event\AuditRecordedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class AuditRecorder
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requests,
        private CorrelationService $correlation,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function record(?User $actor, string $action, string $subject, array $details = []): void
    {
        $r = $this->requests->getCurrentRequest();
        $requestId = $this->correlation->getCorrelationIdentification();
        $this->em->persist(new AuditEvent($actor, $action, $subject, $details, $requestId, $r?->getClientIp() ?? 'cli'));
        $this->dispatcher->dispatch(new AuditRecordedEvent($action, $subject, $actor?->getId(), $details, $requestId));
    }
}
