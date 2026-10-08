<?php

declare(strict_types=1);

namespace App\Service\CommissionRule;

use App\Dto\CommissionRule\Input\ListCommissionRuleHistoryInput;
use App\Dto\CommissionRule\Output\CommissionRuleOutput;
use App\Dto\CommissionRule\Output\ListCommissionRuleHistoryOutput;
use App\Entity\CommissionRule\CommissionRule;
use App\Repository\CommissionRule\CommissionRuleRepository;

final readonly class ListCommissionRuleHistoryService
{
    public function __construct(
        private CommissionRuleRepository $repository,
    ) {
    }

    public function list(ListCommissionRuleHistoryInput $input): ListCommissionRuleHistoryOutput
    {
        $items = array_map(static fn (CommissionRule $rule): CommissionRuleOutput => new CommissionRuleOutput($rule->view()), $this->repository->findPage($input->page));

        return new ListCommissionRuleHistoryOutput($items, $this->repository->count([]), $input->page);
    }
}
