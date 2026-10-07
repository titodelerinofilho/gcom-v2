<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Dto\Commission\Input\ConfirmPaymentInput;
use App\Entity\Commission;
use App\Entity\PaymentLink;
use App\Entity\User;
use App\Exception\BusinessException;
use App\Integration\Winthor\PaymentGateway;
use App\Service\AuditRecorder;
use App\Service\Input;
use App\Service\Money;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ConfirmPayment
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuditRecorder $audit,
        private PaymentGateway $payments,
    ) {
    }

    public function confirm(int $id, ConfirmPaymentInput $input, User $actor): Commission
    {
        $reference = $input->recnum;
        $details = null !== $reference ? $this->payments->fetch($reference) : null;
        $amount = Money::positive($input->amount);
        $paidAt = Input::date(['paidAt' => $input->paidAt], 'paidAt');

        if ($paidAt > new DateTimeImmutable('today')) {
            throw new BusinessException('A data do pagamento não pode ser futura.');
        }

        $notes = $input->notes;

        return $this->em->wrapInTransaction(function () use ($id, $reference, $details, $amount, $paidAt, $notes, $actor, $input) {
            $c = $this->em->find(Commission::class, $id, LockMode::PESSIMISTIC_WRITE) ?? throw new BusinessException('Comissão não encontrada.', 404);

            if ('approved' !== $c->getStatus()) {
                throw new BusinessException('Somente comissões aprovadas podem ser pagas.', 409);
            }

            if (false === $input->manualAmount && $amount !== $c->getNetAmount()) {
                throw new BusinessException('O lançamento deve corresponder ao valor líquido da comissão.');
            }

            $payment = new PaymentLink($c, null, null, $amount, $paidAt, $actor, $notes, $input->manualAmount, $input->manualReason);

            if (null !== $reference) {
                $payment->linkWinthor($reference, $details);
            }

            $this->em->persist($payment);
            $c->setStatus('paid');
            $this->audit->record($actor, 'commission.paid', $c->getCode(), [
                'routine' => null !== $reference ? '749' : null,
                'recnum' => $reference,
                'calculatedAmount' => $payment->getCalculatedAmount(),
                'amount' => $amount,
                'manualAmount' => $payment->isManualAmount(),
                'manualReason' => $payment->getManualReason(),
                'verification' => $payment->getVerification(),
            ]);

            return $c;
        });
    }
}
