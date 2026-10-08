<?php

declare(strict_types=1);

namespace App\Dto\Audit\Output;

use App\Entity\Audit\AuditEvent;

final readonly class AuditOutput
{
    public ?int $id;

    public string $actor;

    public string $action;

    public string $subject;

    public array $details;

    public string $requestId;

    public string $ip;

    public string $createdAt;

    public function __construct(AuditEvent $event)
    {
        $this->id = $event->getId();
        $this->actor = $event->getActor()?->getName() ?? 'Sistema';
        $this->action = $event->getAction();
        $this->subject = $event->getSubject();
        $this->details = $event->getDetails();
        $this->requestId = $event->getRequestId();
        $this->ip = $event->getIp();
        $this->createdAt = $event->getCreatedAt()->format(\DATE_ATOM);
    }
}
