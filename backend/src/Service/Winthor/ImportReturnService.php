<?php

declare(strict_types=1);

namespace App\Service\Winthor;

use App\Dto\Adjustment\Output\AdjustmentOutput;
use App\Dto\Winthor\Input\ImportReturnInput;
use App\Entity\Adjustment\Adjustment;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\MovementGatewayInterface;
use App\Repository\Adjustment\AdjustmentRepository;
use App\Service\Adjustment\ReturnCalculatorService;
use App\Service\Audit\AuditRecorderService;
use App\Service\CommissionRule\GetCurrentCommissionRuleService;

final readonly class ImportReturnService
{
    public function __construct(private MovementGatewayInterface $movements, private ReturnCalculatorService $calculator, private GetCurrentCommissionRuleService $rules, private AuditRecorderService $audit, private AdjustmentRepository $adjustments)
    {
    }

    public function import(ImportReturnInput $input, User $actor): AdjustmentOutput
    {
        $customer = $input->customer;
        $numtransent = $input->numtransent;
        $atg = $input->atg;

        $snapshot = $this->calculator->calculate($this->movements->returns($customer, $atg, $numtransent), $this->rules->current(), $atg);
        $principals = array_unique(array_map(static fn (array $row): string => (string) $row['PRINCIPAL'], $snapshot['sourceRows']));

        if (1 !== count($principals)) {
            throw new BusinessException('A devolução deve pertencer a um único cliente principal.');
        }

        $source = 'winthor:return:'.$numtransent;

        $adjustment = $this->adjustments->save(function () use ($snapshot, $principals, $source, $numtransent, $actor): Adjustment {
            $this->adjustments->lockCustomer(reset($principals));

            foreach ($snapshot['items'] as $line) {
                if (null !== $this->adjustments->findBySource('winthor:cancellation:'.$line['orderNumber'])) {
                    throw new BusinessException('Pedido já estornado por cancelamento. Não registre uma segunda dedução.', 409);
                }
            }

            $this->adjustments->lockSource($source);

            if (null !== $this->adjustments->findBySource($source)) {
                throw new BusinessException('NUMTRANSENT já registrado. A devolução não pode ser deduzida duas vezes.', 409);
            }

            $adjustment = new Adjustment()->setCustomerCode(reset($principals))->setType('return')->setAmount($snapshot['amount'])
                ->setReason('Devolução Winthor calculada pela diferença entre PUNIT e PTABELA1 PSD.')->setSourceReference('NUMTRANSENT '.$numtransent)->setCreatedBy($actor)
                ->captureSource($source, $snapshot);

            $this->adjustments->store($adjustment);
            $this->audit->record($actor, 'return.imported', $source, ['amount' => $snapshot['amount'], 'ruleVersion' => $snapshot['rule']['version'], 'items' => count($snapshot['items'])]);

            return $adjustment;
        });

        return new AdjustmentOutput($adjustment);
    }
}
