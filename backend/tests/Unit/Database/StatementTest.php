<?php

declare(strict_types=1);

namespace App\Tests\Unit\Database;

use App\Database\Connection\DatabaseConnection;
use App\Database\Event\DatabaseQueryEvent;
use App\Database\Statement\Statement;
use App\Exception\Database\DatabaseException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class StatementTest extends TestCase
{
    private array $events = [];

    private function statement(): Statement
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(DatabaseQueryEvent::class, function (DatabaseQueryEvent $event): void {
            $this->events[] = $event;
        });

        return new Statement(new DatabaseConnection('sqlite::memory:', '', '', $dispatcher), $dispatcher);
    }

    public function testParameterizedQueriesReturnResultsAndDispatchTimedEvents(): void
    {
        $statement = $this->statement();
        $statement->query('CREATE TABLE example (name TEXT)')->free();
        $insert = $statement->query('INSERT INTO example (name) VALUES (:name)', ['name' => "O'Reilly"]);
        self::assertSame(1, $insert->rowCount());
        self::assertSame([['name' => "O'Reilly"]], $statement->query('SELECT name FROM example')->fetchAllAssociative());
        self::assertSame('1', $statement->query('SELECT COUNT(*) FROM example')->fetchOne());
        self::assertSame('connect', $this->events[0]->operation);
        self::assertSame(['name' => "O'Reilly"], $this->events[2]->parameters);
        foreach ($this->events as $event) {
            self::assertGreaterThanOrEqual(0, $event->duration);
            self::assertNull($event->exception);
        }
    }

    public function testFailedQueriesDispatchFailureAndPreserveOriginalException(): void
    {
        try {
            $this->statement()->query('SELECT * FROM missing_table');
            self::fail('Expected database exception');
        } catch (DatabaseException $exception) {
            self::assertNotNull($exception->getPrevious());
            self::assertSame('Falha ao executar consulta no banco externo.', $exception->getMessage());
            self::assertNotNull($this->events[array_key_last($this->events)]->exception);
        }
    }

    public function testTransactionCommitsAndRollsBackOnDomainFailure(): void
    {
        $statement = $this->statement();
        $statement->query('CREATE TABLE example (name TEXT)');
        $statement->transaction(static fn () => $statement->query("INSERT INTO example (name) VALUES ('committed')"), readOnly: false);

        try {
            $statement->transaction(static function () use ($statement): void {
                $statement->query("INSERT INTO example (name) VALUES ('rolled_back')");

                throw new RuntimeException('Domain failure');
            }, readOnly: false);
        } catch (RuntimeException $exception) {
            self::assertSame('Domain failure', $exception->getMessage());
        }
        self::assertSame([['name' => 'committed']], $statement->query('SELECT name FROM example')->fetchAllAssociative());
        self::assertContains('commit', array_column($this->events, 'operation'));
        self::assertContains('rollback', array_column($this->events, 'operation'));
    }

    public function testReadOnlySetupFailureRollsBack(): void
    {
        try {
            // SQLite cannot execute Oracle SET TRANSACTION; the failed setup must rollback.
            $this->statement()->transaction(static fn () => 'must not run');
            self::fail('Expected read-only setup failure');
        } catch (DatabaseException) {
            self::assertSame('rollback', $this->events[array_key_last($this->events)]->operation);
        }
    }

    public function testConnectionFailureIsSanitizedAndEmitsAnEvent(): void
    {
        $events = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(DatabaseQueryEvent::class, static function (DatabaseQueryEvent $event) use (&$events): void {
            $events[] = $event;
        });

        try {
            (new DatabaseConnection('missing_driver:private-host', 'private-user', 'secret-password', $dispatcher))->getConnection();
            self::fail('Expected connection failure');
        } catch (DatabaseException $exception) {
            self::assertStringNotContainsString('private', $exception->getMessage());
            self::assertStringNotContainsString('secret-password', $exception->getMessage());
            self::assertSame('connect', $events[0]->operation);
            self::assertNotNull($events[0]->exception);
            self::assertSame([], $events[0]->parameters);
        }
    }
}
