<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Dto\Report\Output\ReportExportContext;
use App\Repository\Report\ReportRepository;

final readonly class ReportRowsService
{
    public function __construct(
        private ReportRepository $repository,
    ) {
    }

    public function rows(ReportExportContext $context): iterable
    {
        $data = $this->repository->export(
            $context->from,
            $context->to,
            $context->filters,
            $context->kind
        );

        foreach ($data as $source) {
            $row = [];

            foreach ($context->columns as $key => $label) {
                $row[$key] = $source[$key] ?? '';
            }

            if (true === array_key_exists('mode', $row)) {
                $row['mode'] = match ($row['mode']) {
                    'atg' => 'ATG (Autoagenciamento)',
                    'normal' => 'Normal',
                    'unspecified' => 'Não informado',
                    default => $row['mode'],
                };
            }

            yield $row;
        }
    }
}
