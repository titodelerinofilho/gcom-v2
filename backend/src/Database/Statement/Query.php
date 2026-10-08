<?php

declare(strict_types=1);

namespace App\Database\Statement;

use App\Exception\Database\DatabaseException;

final readonly class Query
{
    public function __construct(public string $sql, public array $parameters = [])
    {
        if ('' === trim($sql)) {
            throw new DatabaseException('Consulta vazia.');
        }
    }
}
