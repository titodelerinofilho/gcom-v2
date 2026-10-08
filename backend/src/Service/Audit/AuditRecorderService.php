<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\Audit\AuditEvent;
use App\Entity\User\User;
use App\Event\Audit\AuditRecordedEvent;
use App\Repository\Audit\AuditEventRepository;
use App\Service\Logs\CorrelationService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class AuditRecorderService
{
    public function __construct(
        private AuditEventRepository $repository,
        private RequestStack $requests,
        private CorrelationService $correlation,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function record(?User $actor, string $action, string $subject, array $details = []): void
    {
        $request = $this->requests->getCurrentRequest();
        $requestId = $this->correlation->getCorrelationIdentification();
        $this->repository->store(new AuditEvent($actor, $action, $subject, $details, $requestId, $request?->getClientIp() ?? 'cli'));
        $this->dispatcher->dispatch(new AuditRecordedEvent($action, $subject, $actor?->getId(), $details, $requestId));
    }
}
