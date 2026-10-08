<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logs;

use App\EventListener\Logs\RedactionProcessor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class RedactionProcessorTest extends TestCase
{
    public function testSecretsAndDatabaseParametersAreRedactedRecursively(): void
    {
        $record = new LogRecord(new DateTimeImmutable(), 'database', Level::Debug, 'query', ['password' => 'secret', 'params' => ['email' => 'private@example.com'], 'nested' => ['authorization' => 'Bearer hidden'], 'rows' => 2]);
        $clean = (new RedactionProcessor())($record);
        self::assertSame('[redacted]', $clean->context['password']);
        self::assertSame('[redacted]', $clean->context['params']);
        self::assertSame('[redacted]', $clean->context['nested']['authorization']);
        self::assertSame(2, $clean->context['rows']);
    }
}
