<?php

declare(strict_types=1);

namespace App\Integration\Winthor;

use App\Exception\Business\BusinessException;

final class PaymentSnapshotFactory
{
    public function create(string $recnum, array $rows): array
    {
        if (1 !== count($rows)) {
            throw new BusinessException('RECNUM deve identificar exatamente um registro em PCLANC.');
        }

        $row = reset($rows);

        if (false === is_array($row) || (string) ($row['RECNUM'] ?? '') !== $recnum) {
            throw new BusinessException('Consulta de PCLANC deve retornar o RECNUM solicitado.');
        }

        foreach ($row as &$value) {
            if (true === is_resource($value)) {
                $value = stream_get_contents($value);
            }
        }

        unset($value);

        return ['recnum' => $recnum, 'records' => [$row]];
    }
}
