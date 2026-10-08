<?php

declare(strict_types=1);

namespace App\Dto\Commission\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ConfirmPaymentInput
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,12}(?:\.\d{1,2})?$/D', message: 'Informe um valor positivo com até duas casas decimais e ponto.')]
    #[Assert\GreaterThan(0)]
    public string $amount;

    #[Assert\Date]
    public string $paidAt;

    #[Assert\Length(max: 2000)]
    public string $notes;

    public bool $manualAmount;

    #[Assert\Length(max: 2000)]
    #[Assert\When(expression: 'this.manualAmount == false', constraints: [new Assert\Blank()])]
    public ?string $manualReason;

    #[Assert\Regex(pattern: '/^[1-9][0-9]{0,17}$/D')]
    public ?string $recnum;

    public function __construct(
        string $amount,
        string $paidAt,
        ?string $notes = null,
        bool $manualAmount = false,
        ?string $manualReason = null,
        ?string $recnum = null,
    ) {
        $this->amount = trim($amount);
        $this->paidAt = trim($paidAt);
        $this->notes = trim($notes ?? '');
        $this->manualAmount = $manualAmount;
        $this->manualReason = null === $manualReason || '' === trim($manualReason) ? null : trim($manualReason);
        $this->recnum = null === $recnum || '' === trim($recnum) ? null : trim($recnum);
    }
}
