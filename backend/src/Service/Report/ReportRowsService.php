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
        $data = $context->snapshotRows ?? $this->repository->export(
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

            foreach (['status', 'commission_status', 'state', 'type', 'verification'] as $key) {
                if (false === array_key_exists($key, $row)) {
                    continue;
                }

                $row[$key] = match ($row[$key]) {
                    'pending' => 'Pendente', 'approved' => 'Aprovada', 'paid' => 'Paga', 'rejected' => 'Reprovada',
                    'deducted' => 'Deduzido', 'debt' => 'Débito', 'return' => 'Devolução', 'cancellation' => 'Cancelamento',
                    'none' => 'Sem vínculo Winthor', 'manual_reference' => 'Vínculo informado', 'winthor_lookup' => 'Conferido no Winthor',
                    default => $row[$key],
                };
            }

            yield $row;
        }
    }
}
