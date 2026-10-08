<?php

declare(strict_types=1);

namespace App\EventListener\Auth;

use App\Dto\Auth\Output\LogoutOutput;
use App\Repository\Audit\AuditEventRepository;
use App\Service\Audit\AuditRecorderService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class LogoutEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private AuditRecorderService $audit, private AuditEventRepository $repository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [LogoutEvent::class => ['logout', 0]];
    }

    public function logout(LogoutEvent $event): void
    {
        $this->audit->record($event->getToken()?->getUser(), 'auth.logout', 'session');
        $this->repository->savePending();
        $event->setResponse(new JsonResponse(new LogoutOutput(true)));
    }
}
