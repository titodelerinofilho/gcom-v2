<?php

declare(strict_types=1);

namespace App\Database\Doctrine;

use App\Database\Event\DatabaseQueryEvent;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

final class EventStatement extends AbstractStatementMiddleware
{
    private array $parameters = [];

    public function __construct(Statement $statement, private readonly EventDispatcherInterface $dispatcher, private readonly string $sql)
    {
        parent::__construct($statement);
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $this->parameters[$param] = $value;
        parent::bindValue($param, $value, $type);
    }

    public function execute(): Result
    {
        $start = hrtime(true);
        $exception = null;

        try {
            return parent::execute();
        } catch (Throwable $exception) {
            $exception = $exception;

            throw $exception;
        } finally {
            $this->dispatcher->dispatch(new DatabaseQueryEvent($this->sql, $this->parameters, (hrtime(true) - $start) / 1e9, 'postgresql', 'query', $exception));
        }
    }
}
