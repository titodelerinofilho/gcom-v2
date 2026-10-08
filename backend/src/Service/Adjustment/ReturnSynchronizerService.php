<?php

declare(strict_types=1);

namespace App\Service\Adjustment;

use App\Dto\Winthor\Input\ImportReturnInput;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Repository\Adjustment\AdjustmentRepository;
use App\Service\Winthor\ImportReturnService;

final readonly class ReturnSynchronizerService
{
    public function __construct(
        private AdjustmentRepository $adjustments,
        private ImportReturnService $importer,
    ) {
    }

    public function sync(string $customer, bool $atg, User $actor, array $selected, array $rows): void
    {
        $transactions = [];
        foreach ($rows as $row) {
            if (true === isset($row['NUMTRANSENT'])) {
                $transactions[(string) $row['NUMTRANSENT']] = true;
            }
        }

        foreach ($selected as $transaction) {
            $existing = $this->adjustments->findBySource('winthor:return:'.$transaction);

            if (null !== $existing && $customer !== $existing->getCustomerCode()) {
                throw new BusinessException('A devolução selecionada pertence a outro cliente.', 409);
            }

            if (null !== $this->adjustments->findBySource('winthor:return:'.$transaction)?->getCommission()) {
                throw new BusinessException('A devolução selecionada já foi aplicada em outra comissão.', 409);
            }

            if (false === isset($transactions[$transaction]) && null === $this->adjustments->findBySource('winthor:return:'.$transaction)) {
                throw new BusinessException('A devolução selecionada não está mais disponível. Refaça a simulação.', 409);
            }
        }

        $this->adjustments->save(function () use ($customer, $atg, $actor, $selected): void {
            $this->adjustments->lockCustomer($customer);

            foreach ($selected as $transaction) {
                $transaction = (string) $transaction;

                if (null !== $this->adjustments->findBySource('winthor:return:'.$transaction)) {
                    continue;
                }

                $this->importer->import(new ImportReturnInput($customer, $atg, $transaction), $actor);
            }
        });
    }
}
