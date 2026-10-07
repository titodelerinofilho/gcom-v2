<?php

declare(strict_types=1);

namespace App\Database\Statement;

use App\Database\Statement\Oracle\NumberNormalizer;
use PDO;
use PDOStatement;

final readonly class Result
{
    private array $numberColumns;

    private bool $firstColumnIsNumber;

    public function __construct(private PDOStatement $statement, bool $normalizeOracleNumbers = false)
    {
        $numberColumns = [];
        $firstColumnIsNumber = false;

        if (true === $normalizeOracleNumbers) {
            for ($index = 0; $index < $statement->columnCount(); ++$index) {
                $metadata = $statement->getColumnMeta($index);

                if (false !== $metadata && 'NUMBER' === ($metadata['native_type'] ?? null)) {
                    $numberColumns[$metadata['name']] = true;

                    if (0 === $index) {
                        $firstColumnIsNumber = true;
                    }
                }
            }
        }

        $this->numberColumns = $numberColumns;
        $this->firstColumnIsNumber = $firstColumnIsNumber;
    }

    public function fetchAssociative(): array|false
    {
        $row = $this->statement->fetch(PDO::FETCH_ASSOC);

        return false === $row ? false : $this->materialize($row);
    }

    public function fetchAllAssociative(): array
    {
        return array_map($this->materialize(...), $this->statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function fetchOne(): mixed
    {
        $value = $this->statement->fetchColumn();

        if (true === $this->firstColumnIsNumber && false !== $value && null !== $value) {
            return NumberNormalizer::normalize((string) $value);
        }

        return is_resource($value) ? stream_get_contents($value) : $value;
    }

    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }

    public function free(): void
    {
        $this->statement->closeCursor();
    }

    private function materialize(array $row): array
    {
        foreach ($row as $column => &$value) {
            if (is_resource($value)) {
                $value = stream_get_contents($value);
            }

            if (null !== $value && true === isset($this->numberColumns[$column])) {
                $value = NumberNormalizer::normalize((string) $value);
            }
        }

        return $row;
    }
}
