<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logs;

use App\EventListener\Logs\ExceptionEventListener;
use App\Service\Logs\CorrelationService;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ExceptionLogListenerTest extends TestCase
{
    public function testExceptionPassesCriticalThresholdAsJson(): void
    {
        $stream = fopen('php://memory', 'w+');
        $handler = new StreamHandler($stream, Logger::CRITICAL);
        $handler->setFormatter(new JsonFormatter());
        $logger = new Logger('exceptions', [$handler]);
        $stack = new RequestStack();
        $request = Request::create('/api/reports');
        $stack->push($request);
        $listener = new ExceptionEventListener($logger, new CorrelationService($stack), self::createStub(Security::class), true);
        $listener(new ExceptionEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new RuntimeException('private-password')));
        rewind($stream);
        $line = stream_get_contents($stream);
        $record = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('exceptions', $record['channel']);
        self::assertSame('CRITICAL', $record['level_name']);
        self::assertSame($request->attributes->get('request_id'), $record['context']['request_id']);
        self::assertStringNotContainsString('private-password', $line);
        fclose($stream);
    }
}
