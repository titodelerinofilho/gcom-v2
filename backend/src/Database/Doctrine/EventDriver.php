<?php

declare(strict_types=1);

namespace App\Database\Doctrine;

use App\Database\Event\DatabaseQueryEvent;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

final class EventDriver extends AbstractDriverMiddleware
{
    public function __construct(Driver $driver, private readonly EventDispatcherInterface $dispatcher)
    {
        parent::__construct($driver);
    }

    public function connect(#[SensitiveParameter] array $params): Connection
    {
        $start = hrtime(true);
        $exception = null;

        try {
            return new EventConnection(parent::connect($params), $this->dispatcher);
        } catch (Throwable $exception) {
            $exception = $exception;

            throw $exception;
        } finally {
            $this->dispatcher->dispatch(new DatabaseQueryEvent('CONNECT', [], (hrtime(true) - $start) / 1e9, 'postgresql', 'connect', $exception));
        }
    }
}
