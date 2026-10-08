<?php

declare(strict_types=1);

namespace App\Service\CommissionRule;

use App\Dto\CommissionRule\Input\CreateCommissionRuleInput;
use App\Dto\CommissionRule\Output\CommissionRuleOutput;
use App\Entity\CommissionRule\CommissionRule;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Repository\CommissionRule\CommissionRuleRepository;
use App\Service\Audit\AuditRecorderService;
use App\Service\Finance\MoneyService;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class CreateCommissionRuleService
{
    public function __construct(
        private AuditRecorderService $audit,
        private AuthorizationCheckerInterface $authorization,
        private CommissionRuleRepository $repository,
        private GetCurrentCommissionRuleService $currentRule,
    ) {
    }

    public function save(CreateCommissionRuleInput $input, User $actor): CommissionRuleOutput
    {
        if (false === $this->authorization->isGranted('ROLE_ADMIN')) {
            throw new BusinessException('Somente administradores podem alterar a fórmula.', 403);
        }

        $percentage = MoneyService::decimal($input->percentage, 4);

        $basis = $input->basis;
        $contexts = $input->priceContexts;
        $atgPercentage = MoneyService::decimal($input->atgPercentage, 4);
        $returnPercentage = MoneyService::decimal($input->returnPercentage, 4);
        $atgReturnPercentage = MoneyService::decimal($input->atgReturnPercentage, 4);

        $reason = $input->reason;

        $settings = ['percentage' => $percentage, 'basis' => $basis, 'priceContexts' => $contexts, 'atgPercentage' => $atgPercentage, 'returnPercentage' => $returnPercentage, 'atgReturnPercentage' => $atgReturnPercentage, 'subtractFreight' => $input->subtractFreight, 'applyReferenceDiscount' => $input->applyReferenceDiscount];

        $rule = $this->repository->save(function () use ($settings, $reason, $actor, $input) {
            $this->repository->lockVersion();
            $current = $this->currentRule->current();

            if ($input->expectedVersion !== $current['version']) {
                throw new BusinessException('A regra foi alterada por outro administrador. Recarregue antes de salvar.', 409);
            }

            $rule = new CommissionRule($settings, $reason, $actor);
            $this->repository->store($rule);
            $this->audit->record($actor, 'commission_rule.changed', 'commission-rule:'.$rule->view()['version'], ['previous' => $current, 'current' => $rule->view()]);

            return $rule->view();
        });

        return new CommissionRuleOutput($rule);
    }
}
