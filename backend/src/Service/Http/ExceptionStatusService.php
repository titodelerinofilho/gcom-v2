<?php

declare(strict_types=1);

namespace App\Service\Http;

use App\Exception\Business\BusinessException;
use App\Exception\Database\DatabaseException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Throwable;

final class ExceptionStatusService
{
    public static function resolve(Throwable $exception, bool $authenticated): int
    {
        return match (true) {
            $exception instanceof ExtraAttributesException => 422,
            $exception instanceof BusinessException => $exception->statusCode,
            $exception instanceof DatabaseException => 503,
            $exception instanceof UniqueConstraintViolationException => 409,
            $exception instanceof AccessDeniedException => true === $authenticated ? 403 : 401,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
    }
}
