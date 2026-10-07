<?php

declare(strict_types=1);

namespace App\Database\Statement;

use App\Database\Connection\DatabaseConnection;
use App\Database\Event\DatabaseQueryEvent;
use App\Exception\DatabaseException;
use PDOException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

final readonly class Statement
{
    public function __construct(private DatabaseConnection $connection, private EventDispatcherInterface $dispatcher)
    {
    }

    public function query(string $sql, array $parameters = []): Result
    {
        $query = new Query($sql, $parameters);
        $start = hrtime(true);
        $exception = null;

        try {
            $statement = $this->connection->getConnection()->prepare($query->sql);
            $statement->execute($query->parameters);

            return new Result($statement, normalizeOracleNumbers: 'oracle' === $this->connection->getName());
        } catch (Throwable $e) {
            $exception = $e;

            if ($e instanceof PDOException) {
                throw new DatabaseException('Falha ao executar consulta no banco externo.', previous: $e);
            }

            throw $e;
        } finally {
            $this->dispatcher->dispatch(new DatabaseQueryEvent($query->sql, $query->parameters, (hrtime(true) - $start) / 1e9, $this->connection->getName(), 'query', $exception));
        }
    }

    public function transaction(callable $operation, bool $readOnly = true): mixed
    {
        return (new Transaction($this->connection, $this->dispatcher))->run($operation, $this, $readOnly);
    }
}
