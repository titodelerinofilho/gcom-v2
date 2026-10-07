<?php

declare(strict_types=1);

namespace App\Database\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMiddleware]
final readonly class QueryMiddleware implements Middleware
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new EventDriver($driver, $this->dispatcher);
    }
}
