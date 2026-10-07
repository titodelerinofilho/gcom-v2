<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\AuditRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class LogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(private AuditRecorder $audit, private EntityManagerInterface $em)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [LogoutEvent::class => ['logout', 0]];
    }

    public function logout(LogoutEvent $event): void
    {
        $this->audit->record($event->getToken()?->getUser(), 'auth.logout', 'session');
        $this->em->flush();
        $event->setResponse(new JsonResponse(['ok' => true]));
    }
}
