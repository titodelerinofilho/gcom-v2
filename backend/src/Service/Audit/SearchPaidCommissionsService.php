<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Dto\Audit\Input\SearchPaidCommissionsInput;
use App\Dto\Audit\Output\PaidCommissionAuditSummaryOutput;
use App\Dto\Audit\Output\SearchPaidCommissionsOutput;
use App\Entity\Commission\Commission;
use App\Repository\Commission\CommissionRepository;

final readonly class SearchPaidCommissionsService
{
    public function __construct(private CommissionRepository $commissions)
    {
    }

    public function search(SearchPaidCommissionsInput $input): SearchPaidCommissionsOutput
    {
        $result = $this->commissions->findPaidPage(trim($input->query), $input->page);

        return new SearchPaidCommissionsOutput(array_map(static fn (Commission $commission): PaidCommissionAuditSummaryOutput => new PaidCommissionAuditSummaryOutput($commission), $result['items']), $result['total'], $input->page);
    }
}
