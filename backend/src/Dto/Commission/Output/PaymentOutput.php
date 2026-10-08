<?php

declare(strict_types=1);

namespace App\Dto\Commission\Output;

use App\Entity\Commission\PaymentLink;

final readonly class PaymentOutput
{
    public string $amount;

    public string $calculatedAmount;

    public bool $manualAmount;

    public ?string $manualReason;

    public string $paidAt;

    public string $confirmedAt;

    public string $confirmedBy;

    public string $verification;

    public string $notes;

    public ?array $winthor;

    public function __construct(PaymentLink $payment)
    {
        $this->amount = $payment->getAmount();
        $this->calculatedAmount = $payment->getCalculatedAmount();
        $this->manualAmount = $payment->isManualAmount();
        $this->manualReason = $payment->getManualReason();

        $this->paidAt = $payment->getPaidAt()->format('Y-m-d');
        $this->confirmedAt = $payment->getConfirmedAt()->format(\DATE_ATOM);
        $this->confirmedBy = $payment->getConfirmedBy()->getName();
        $this->verification = $payment->getVerification();
        $this->notes = $payment->getNotes();

        $this->winthor = null === $payment->getReference() ? null : [
            'routine' => '749',
            'recnum' => $payment->getReference(),
            'verification' => $payment->getVerification(),
            'details' => $payment->getWinthorDetails(),
        ];
    }
}
