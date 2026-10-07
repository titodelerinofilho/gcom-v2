<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\BusinessException;
use App\Exception\DatabaseException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

final class ExceptionStatus
{
    public static function resolve(Throwable $exception, bool $authenticated): int
    {
        return match (true) {
            $exception instanceof BusinessException => $exception->statusCode,
            $exception instanceof DatabaseException => 503,
            $exception instanceof UniqueConstraintViolationException => 409,
            $exception instanceof AccessDeniedException => $authenticated ? 403 : 401,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
    }
}
