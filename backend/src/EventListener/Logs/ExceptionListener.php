<?php

declare(strict_types=1);

namespace App\EventListener\Logs;

use App\Service\CorrelationService;
use App\Service\ExceptionStatus;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 20)]
final readonly class ExceptionListener
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.exceptions')]
        private LoggerInterface $logger,
        private CorrelationService $correlation,
        private Security $security,
        #[Autowire('%env(bool:LOG_EXCEPTIONS)%')]
        private bool $enabled,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->enabled) {
            return;
        }
        $exception = $event->getThrowable();
        $status = ExceptionStatus::resolve($exception, $exception instanceof AccessDeniedException && null !== $this->security->getUser());
        $this->logger->critical('http.exception', ['request_id' => $this->correlation->getCorrelationIdentification(), 'status' => $status, 'exception_class' => $exception::class, 'file' => $exception->getFile(), 'line' => $exception->getLine()]);
    }
}
