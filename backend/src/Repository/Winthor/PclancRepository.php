<?php

declare(strict_types=1);

namespace App\Repository\Winthor;

use App\Database\Statement\Statement;
use App\Exception\Business\BusinessException;

final readonly class PclancRepository
{
    public function __construct(private Statement $statement)
    {
    }

    public function findByRecnum(string $recnum): array
    {
        if (false === preg_match('/^[1-9][0-9]{0,17}$/D', $recnum)) {
            throw new BusinessException('RECNUM inválido.');
        }

        $sql = <<<'SQL'
            SELECT L.*
            FROM PCLANC L
            WHERE L.RECNUM = :recnum
            SQL;

        return $this->statement->transaction(fn (): array => $this->statement->query($sql, ['recnum' => $recnum])->fetchAllAssociative());
    }
}
