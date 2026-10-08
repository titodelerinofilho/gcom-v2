<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Dto\Audit\Output\AuditOutput;
use App\Dto\Audit\Output\CommissionAuditOutput;
use App\Dto\Audit\Output\PaidCommissionAuditSummaryOutput;
use App\Dto\Audit\Output\PaymentAuditOutput;
use App\Dto\Commission\Output\PaymentOutput;
use App\Entity\Audit\AuditEvent;
use App\Entity\Commission\PaymentLink;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Exception\Database\DatabaseException;
use App\Integration\Winthor\OrderGatewayInterface;
use App\Integration\Winthor\PaymentGateway;
use App\Repository\Audit\AuditEventRepository;
use App\Repository\Commission\CommissionRepository;
use App\Repository\Commission\PaymentLinkRepository;
use DateTimeImmutable;

final readonly class VerifyCommissionService
{
    public function __construct(private CommissionRepository $commissions, private PaymentLinkRepository $payments, private OrderGatewayInterface $winthor, private CompareOrderSnapshotService $comparison, private AuditRecorderService $audit, private AuditEventRepository $events, private PaymentGateway $winthorPayments, private CompareCommissionPaymentService $paymentComparison)
    {
    }

    public function verify(int $id, User $actor): CommissionAuditOutput
    {
        $commission = $this->commissions->find($id) ?? throw new BusinessException('Comissão não encontrada.', 404);

        if ('paid' !== $commission->getStatus()) {
            throw new BusinessException('Selecione uma comissão paga para realizar esta conferência.', 409);
        }
        $payment = $this->payments->findForCommission($commission);

        if (null === $payment) {
            throw new BusinessException('A comissão não possui pagamento confirmado.', 409);
        }

        $orders = [];
        $ids = [];
        foreach ($commission->getOrders() as $order) {
            $orders[] = $this->comparison->compare($order, $this->winthor->inspect($order->getOrderNumber()));
            $ids[] = $order->getId();
        }
        $paymentCheck = $this->checkPayment($payment);
        $checkedAt = (new DateTimeImmutable())->format(\DATE_ATOM);
        $this->audit->record($actor, 'commission.verified', $commission->getCode(), ['checkedAt' => $checkedAt, 'orders' => json_decode(json_encode($orders, \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR), 'paymentCheck' => null === $paymentCheck ? null : json_decode(json_encode($paymentCheck, \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR)]);
        $this->events->savePending();
        $subjects = [$commission->getCode(), ...$this->commissions->rejectedCodesForOrders($ids)];
        $events = array_map(static fn (AuditEvent $event): AuditOutput => new AuditOutput($event), $this->events->findTimeline($subjects));

        return new CommissionAuditOutput(new PaidCommissionAuditSummaryOutput($commission), new PaymentOutput($payment), $checkedAt, $orders, $events, $paymentCheck);
    }

    private function checkPayment(PaymentLink $payment): ?PaymentAuditOutput
    {
        if (null === $payment->getReference()) {
            return null;
        }

        $details = null;
        $notice = null;

        try {
            $details = $this->winthorPayments->fetch($payment->getReference());
        } catch (DatabaseException|BusinessException) {
            $notice = 'Não foi possível consultar o lançamento da rotina 749 no Winthor.';
        }

        $source = 'current';

        if (null === $details) {
            $details = $payment->getWinthorDetails();
            $source = null === $details ? 'unavailable' : 'snapshot';
            $notice ??= 'A consulta atual da rotina 749 não está disponível.';
            $notice .= null === $details ? ' Os valores do Winthor não puderam ser conferidos.' : ' A comparação utiliza os dados preservados no vínculo do pagamento.';
        }

        return $this->paymentComparison->compare($payment, $details, $source, $notice);
    }
}
