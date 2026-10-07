<?php

declare(strict_types=1);

namespace App\EventListener\Logs;

use App\Service\CorrelationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 110)]
final readonly class RequestListener
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.requests')]
        private LoggerInterface $logger,
        private CorrelationService $correlation,
        #[Autowire('%env(bool:LOG_REQUESTS)%')]
        private bool $enabled,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $id = $this->correlation->getCorrelationIdentification();

        if (!$this->enabled) {
            return;
        }
        $request = $event->getRequest();
        $this->logger->info('http.request', ['request_id' => $id, 'method' => $request->getMethod(), 'path' => $request->getPathInfo(), 'ip' => $request->getClientIp(), 'headers' => ['content-type' => $request->headers->get('Content-Type'), 'accept' => $request->headers->get('Accept')], 'content_length' => $request->headers->get('Content-Length')]);
    }
}
