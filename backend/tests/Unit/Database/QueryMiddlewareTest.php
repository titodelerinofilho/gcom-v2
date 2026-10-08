<?php

declare(strict_types=1);

namespace App\Tests\Unit\Database;

use App\Database\Doctrine\QueryMiddleware;
use App\Database\Event\DatabaseQueryEvent;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class QueryMiddlewareTest extends TestCase
{
    public function testDoctrineEmitsEventsForPreparedQueriesTransactionsAndFailures(): void
    {
        $events = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(DatabaseQueryEvent::class, static function (DatabaseQueryEvent $event) use (&$events): void {
            $events[] = $event;
        });
        $configuration = new Configuration();
        $configuration->setMiddlewares([new QueryMiddleware($dispatcher)]);
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $db->executeStatement('CREATE TABLE example (name TEXT)');
        $db->beginTransaction();
        $db->executeStatement('INSERT INTO example (name) VALUES (?)', ['private-name']);
        $db->commit();
        self::assertSame('private-name', $db->fetchOne('SELECT name FROM example'));
        self::assertContains('connect', array_column($events, 'operation'));
        self::assertContains('commit', array_column($events, 'operation'));
        self::assertSame([1 => 'private-name'], $events[3]->parameters);

        try {
            $db->executeQuery('SELECT * FROM missing_table');
            self::fail('Doctrine must retain its exception');
        } catch (Exception) {
            self::assertNotNull($events[array_key_last($events)]->exception);
        }
    }
}
