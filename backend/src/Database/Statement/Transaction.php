<?php

declare(strict_types=1);

namespace App\Database\Statement;

use App\Database\Connection\DatabaseConnection;
use App\Database\Event\DatabaseQueryEvent;
use App\Exception\Database\DatabaseException;
use PDOException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

final readonly class Transaction
{
    public function __construct(private DatabaseConnection $connection, private EventDispatcherInterface $dispatcher)
    {
    }

    public function run(callable $operation, Statement $statement, bool $readOnly): mixed
    {
        $pdo = $this->connection->getConnection();

        if (true === $pdo->inTransaction()) {
            throw new DatabaseException('Transações aninhadas no banco externo não são permitidas.');
        }

        $this->control('begin', $pdo->beginTransaction(...));

        try {
            if (true === $readOnly) {
                $readOnlySql = 'SET TRANSACTION READ ONLY';
                $statement->query($readOnlySql)->free();
            }

            $result = $operation();
            $this->control('commit', $pdo->commit(...));

            return $result;
        } catch (Throwable $exception) {
            if (true === $pdo->inTransaction()) {
                $this->control('rollback', $pdo->rollBack(...));
            }

            throw $exception;
        }
    }

    private function control(string $operation, callable $action): void
    {
        $start = hrtime(true);
        $exception = null;

        try {
            $action();
        } catch (PDOException $exception) {
            $exception = $exception;

            throw new DatabaseException('Falha no controle da transação externa.', previous: $exception);
        } finally {
            $this->dispatcher->dispatch(new DatabaseQueryEvent(strtoupper($operation), [], (hrtime(true) - $start) / 1e9, $this->connection->getName(), $operation, $exception));
        }
    }
}
