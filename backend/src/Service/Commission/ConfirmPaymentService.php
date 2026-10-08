<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Dto\Commission\Input\ConfirmPaymentInput;
use App\Dto\Commission\Output\ConfirmedPaymentOutput;
use App\Entity\Commission\PaymentLink;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\PaymentGateway;
use App\Repository\Commission\CommissionRepository;
use App\Repository\Commission\PaymentLinkRepository;
use App\Service\Audit\AuditRecorderService;
use App\Service\Finance\MoneyService;
use DateTimeImmutable;

final readonly class ConfirmPaymentService
{
    public function __construct(
        private PaymentLinkRepository $paymentLinks,
        private AuditRecorderService $audit,
        private PaymentGateway $payments,
        private CommissionRepository $commissions,
    ) {
    }

    public function confirm(int $id, ConfirmPaymentInput $input, User $actor): ConfirmedPaymentOutput
    {
        $reference = $input->recnum;
        $details = null !== $reference ? $this->payments->fetch($reference) : null;
        $amount = MoneyService::positive($input->amount);
        $paidAt = new DateTimeImmutable($input->paidAt);

        if ($paidAt > new DateTimeImmutable('today')) {
            throw new BusinessException('A data do pagamento não pode ser futura.');
        }

        $notes = $input->notes;

        $commission = $this->commissions->save(function () use ($id, $reference, $details, $amount, $paidAt, $notes, $actor, $input) {
            $commission = $this->commissions->locked($id);

            if ('approved' !== $commission->getStatus()) {
                throw new BusinessException('Somente comissões aprovadas podem ser pagas.', 409);
            }

            if (false === $input->manualAmount && $amount !== $commission->getNetAmount()) {
                throw new BusinessException('O lançamento deve corresponder ao valor líquido da comissão.');
            }

            $payment = new PaymentLink($commission, null, null, $amount, $paidAt, $actor, $notes, $input->manualAmount, $input->manualReason);

            if (null !== $reference) {
                $payment->linkWinthor($reference, $details);
            }

            $this->paymentLinks->store($payment);
            $commission->setStatus('paid');
            $this->audit->record($actor, 'commission.paid', $commission->getCode(), [
                'routine' => null !== $reference ? '749' : null,
                'recnum' => $reference,
                'calculatedAmount' => $payment->getCalculatedAmount(),
                'amount' => $amount,
                'manualAmount' => $payment->isManualAmount(),
                'manualReason' => $payment->getManualReason(),
                'verification' => $payment->getVerification(),
            ]);

            return $commission;
        });

        return new ConfirmedPaymentOutput($commission->getId(), $commission->getStatus());
    }
}
