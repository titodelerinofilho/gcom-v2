<?php

declare(strict_types=1);

namespace App\Database\Doctrine;

use App\Database\Event\DatabaseQueryEvent;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

final class EventConnection extends AbstractConnectionMiddleware
{
    public function __construct(Connection $connection, private readonly EventDispatcherInterface $dispatcher)
    {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        $start = hrtime(true);

        try {
            return new EventStatement(parent::prepare($sql), $this->dispatcher, $sql);
        } catch (Throwable $e) {
            $this->dispatcher->dispatch(new DatabaseQueryEvent($sql, [], (hrtime(true) - $start) / 1e9, 'postgresql', 'prepare', $e));

            throw $e;
        }
    }

    public function query(string $sql): Result
    {
        return $this->execute($sql, 'query', fn () => parent::query($sql));
    }

    public function exec(string $sql): int|string
    {
        return $this->execute($sql, 'exec', fn () => parent::exec($sql));
    }

    public function beginTransaction(): void
    {
        $this->execute('BEGIN', 'begin', fn () => parent::beginTransaction());
    }

    public function commit(): void
    {
        $this->execute('COMMIT', 'commit', fn () => parent::commit());
    }

    public function rollBack(): void
    {
        $this->execute('ROLLBACK', 'rollback', fn () => parent::rollBack());
    }

    private function execute(string $sql, string $operation, callable $action): mixed
    {
        $start = hrtime(true);
        $exception = null;

        try {
            return $action();
        } catch (Throwable $e) {
            $exception = $e;

            throw $e;
        } finally {
            $this->dispatcher->dispatch(new DatabaseQueryEvent($sql, [], (hrtime(true) - $start) / 1e9, 'postgresql', $operation, $exception));
        }
    }
}
