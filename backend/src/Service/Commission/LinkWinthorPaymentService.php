<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Dto\Commission\Input\LinkWinthorPaymentInput;
use App\Dto\Commission\Output\CommissionOutput;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\PaymentGateway;
use App\Repository\Commission\CommissionRepository;
use App\Repository\Commission\PaymentLinkRepository;
use App\Service\Audit\AuditRecorderService;

final readonly class LinkWinthorPaymentService
{
    public function __construct(
        private AuditRecorderService $audit,
        private PaymentGateway $payments,
        private CommissionRepository $commissions,
        private PaymentLinkRepository $paymentLinks,
    ) {
    }

    public function linkWinthor(int $id, LinkWinthorPaymentInput $input, User $actor): CommissionOutput
    {
        $recnum = $input->recnum;
        $details = $this->payments->fetch($recnum);
        $commission = $this->commissions->save(function () use ($id, $recnum, $details, $actor) {
            $commission = $this->commissions->locked($id);

            if ('paid' !== $commission->getStatus()) {
                throw new BusinessException('Confirme o pagamento antes de vincular o RECNUM.', 409);
            }

            $payment = $this->paymentLinks->findForCommission($commission);

            if (null === $payment) {
                throw new BusinessException('Registro do pagamento não encontrado.', 409);
            }

            $payment->linkWinthor($recnum, $details);
            $this->audit->record($actor, 'payment.winthor_linked', $commission->getCode(), ['routine' => '749', 'recnum' => $recnum, 'verification' => $payment->getVerification()]);

            return $commission;
        });

        return new CommissionOutput($commission);
    }
}
