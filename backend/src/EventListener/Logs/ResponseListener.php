<?php

declare(strict_types=1);

namespace App\EventListener\Logs;

use App\Service\CorrelationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE, priority: -20)]
final readonly class ResponseListener
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.responses')]
        private LoggerInterface $logger,
        private CorrelationService $correlation,
        #[Autowire('%env(bool:LOG_RESPONSES)%')]
        private bool $enabled,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->enabled) {
            return;
        }
        $request = $event->getRequest();
        $response = $event->getResponse();
        $bytes = $response->headers->get('Content-Length');
        $size = null === $bytes ? ($response instanceof BinaryFileResponse ? $response->getFile()->getSize() : strlen($response->getContent() ?: '')) : (int) $bytes;
        $this->logger->info('http.response', ['request_id' => $this->correlation->getCorrelationIdentification(), 'status' => $response->getStatusCode(), 'content_type' => $response->headers->get('Content-Type'), 'bytes' => $size, 'duration_ms' => round((microtime(true) - $request->attributes->get('started_at', microtime(true))) * 1000)]);
    }
}
