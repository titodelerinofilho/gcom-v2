<?php

declare(strict_types=1);

namespace App\Event;

final readonly class AuditRecordedEvent
{
    public function __construct(public string $action, public string $subject, public ?int $actorId, public array $details, public string $requestId)
    {
    }
}
