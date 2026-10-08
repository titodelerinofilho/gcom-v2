<?php

declare(strict_types=1);

namespace App\Service\CommissionRule;

use App\Dto\CommissionRule\Output\CommissionRuleOutput;
use App\Exception\Business\BusinessException;
use App\Repository\CommissionRule\CommissionRuleRepository;

final readonly class GetCurrentCommissionRuleService
{
    public function __construct(
        private CommissionRuleRepository $repository,
    ) {
    }

    public function current(): array
    {
        $rule = $this->repository->findCurrent();

        if (null === $rule) {
            throw new BusinessException('Configure a regra de comissão antes de gerar comissões.', 409);
        }

        return $rule->view();
    }

    public function get(): CommissionRuleOutput
    {
        return new CommissionRuleOutput($this->current());
    }
}
