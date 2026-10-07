<?php

declare(strict_types=1);

namespace App\EventListener\Logs;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

final class RedactionProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(context: $this->clean($record->context), extra: $this->clean($record->extra));
    }

    private function clean(array $values): array
    {
        foreach ($values as $key => &$value) {
            if (preg_match('/password|secret|token|cookie|authorization|params|parameters|dsn|database_url|email/i', (string) $key)) {
                $value = '[redacted]';
            } elseif ($value instanceof Throwable) {
                $value = ['class' => $value::class, 'code' => $value->getCode()];
            } elseif (is_array($value)) {
                $value = $this->clean($value);
            }
        }

        return $values;
    }
}
