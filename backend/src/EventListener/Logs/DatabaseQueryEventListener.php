<?php

declare(strict_types=1);

namespace App\EventListener\Logs;

use App\Database\Event\DatabaseQueryEvent;
use App\Service\Logs\CorrelationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class DatabaseQueryEventListener
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.database_queries')]
        private LoggerInterface $logger,
        private CorrelationService $correlation,
        #[Autowire('%env(bool:LOG_DATABASE)%')]
        private bool $enabled,
    ) {
    }

    public function __invoke(DatabaseQueryEvent $event): void
    {
        if (false === $this->enabled) {
            return;
        }
        // Log the query shape, never credentials, literal data or parameter values.
        $sql = preg_replace("/'(?:''|[^'])*'/s", "'?'", $event->sql);
        $sql = preg_replace('/(\$(?:[a-zA-Z_][a-zA-Z_0-9]*)?\$).*?\1/s', "'?'", $sql);
        $sql = preg_replace('~--[^\r\n]*|/\*.*?\*/~s', '', $sql);
        $this->logger->log(null === $event->exception ? 'info' : 'error', 'database.query', ['request_id' => $this->correlation->getCorrelationIdentification(), 'connection' => $event->connection, 'operation' => $event->operation, 'sql' => $sql, 'parameter_count' => count($event->parameters), 'duration_ms' => round($event->duration * 1000, 3), 'successful' => null === $event->exception, 'exception_class' => $event->exception ? $event->exception::class : null, 'exception_code' => $event->exception?->getCode()]);
    }
}
