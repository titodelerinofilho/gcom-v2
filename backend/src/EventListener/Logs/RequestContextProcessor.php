<?php

declare(strict_types=1);

namespace App\EventListener\Logs;

use App\Service\Logs\CorrelationService;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RequestContextProcessor implements ProcessorInterface
{
    public function __construct(private CorrelationService $correlation, #[Autowire('%kernel.environment%')] private string $environment = 'dev')
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: [
            ...$record->extra,
            'service' => 'gcom-backend',
            'environment' => $this->environment,
            'request_id' => $this->correlation->getCorrelationIdentification(),
        ]);
    }
}
