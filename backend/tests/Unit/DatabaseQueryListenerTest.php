<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Database\Event\DatabaseQueryEvent;
use App\EventListener\Logs\DatabaseQueryListener;
use App\Service\CorrelationService;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class DatabaseQueryListenerTest extends TestCase
{
    public function testLoggingUsesCorrelationWithoutLiteralOrParameterValues(): void
    {
        $requests = new RequestStack();
        $request = Request::create('/api/login');
        $requests->push($request);
        $correlation = new CorrelationService($requests);
        $handler = new TestHandler();
        $logger = new Logger('database', [$handler]);
        $listener = new DatabaseQueryListener($logger, $correlation, true);
        $listener(new DatabaseQueryEvent("SELECT 'literal-secret' WHERE name = :name", ['name' => 'private@example.test'], 0.025, 'oracle'));
        $record = $handler->getRecords()[0];
        self::assertSame($request->attributes->get('request_id'), $record->context['request_id']);
        self::assertSame('INFO', $record->level->getName());
        self::assertSame(25.0, $record->context['duration_ms']);
        self::assertSame(1, $record->context['parameter_count']);
        self::assertStringNotContainsString('literal-secret', $record->context['sql']);
        self::assertStringNotContainsString('private@example.test', json_encode($record->context));
        $listener(new DatabaseQueryEvent('SELECT ?', [], 0.01, 'postgresql', exception: new RuntimeException('confidential-message')));
        self::assertTrue($handler->hasErrorRecords());
        self::assertStringNotContainsString('confidential-message', json_encode($handler->getRecords()[1]->context));
        (new DatabaseQueryListener($logger, $correlation, false))(new DatabaseQueryEvent('SELECT 1', [], 0, 'oracle'));
        self::assertCount(2, $handler->getRecords());
    }
}
