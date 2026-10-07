<?php

declare(strict_types=1);

namespace App\EventListener\Logs;

use App\Event\AuditRecordedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class AuditListener
{
    public function __construct(#[Autowire(service: 'monolog.logger.audit')] private LoggerInterface $logger)
    {
    }

    public function __invoke(AuditRecordedEvent $event): void
    {
        $this->logger->info($event->action, ['subject' => $event->subject, 'actor_id' => $event->actorId, 'details' => $event->details, 'request_id' => $event->requestId]);
    }
}
