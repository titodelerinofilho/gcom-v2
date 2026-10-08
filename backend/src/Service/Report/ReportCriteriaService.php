<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Dto\Report\Input\GetReportSummaryInput;
use App\Dto\Report\Input\ReportQueryInput;
use App\Exception\Business\BusinessException;
use DateTimeImmutable;

final readonly class ReportCriteriaService
{
    public function range(ReportQueryInput|GetReportSummaryInput $input): array
    {
        $from = new DateTimeImmutable($input->from);
        $to = new DateTimeImmutable($input->to);

        if ($to < $from || 3660 < $from->diff($to)->days) {
            throw new BusinessException('Período inválido (máximo 10 anos).');
        }

        return [$from->format('Y-m-d'), $to->modify('+1 day')->format('Y-m-d')];
    }

    /** @return array<string, string> */
    public function filters(ReportQueryInput $input, string $kind): array
    {
        $filters = [];

        foreach (['customer', 'orderNumber', 'status', 'mode', 'type', 'state', 'dateBasis'] as $field) {
            if (null !== $input->{$field}) {
                $filters[$field] = $input->{$field};
            }
        }

        $dateBases = 'commissions' === $kind ? ['created', 'paid'] : ['created', 'applied'];

        if (null !== $input->dateBasis && false === in_array($input->dateBasis, $dateBases, true)) {
            throw new BusinessException('Filtro inválido: dateBasis.');
        }

        if (('commissions' === $kind && (true === isset($filters['state']) || true === isset($filters['type']))) || ('adjustments' === $kind && true === isset($filters['orderNumber']))) {
            throw new BusinessException('Filtro incompatível com o relatório escolhido.');
        }

        return $filters;
    }
}
