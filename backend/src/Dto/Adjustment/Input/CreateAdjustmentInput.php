<?php

declare(strict_types=1);

namespace App\Dto\Adjustment\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateAdjustmentInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    #[Assert\Regex(pattern: '/^[0-9]+$/D', message: 'Código do cliente inválido.')]
    public string $customerCode;

    #[Assert\Choice(choices: ['debt', 'return'], message: 'Tipo inválido.')]
    public string $type;

    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(pattern: '/^[0-9]{1,12}(?:\.[0-9]{1,6})?$/D', message: 'Informe valores decimais positivos com ponto, sem separador de milhares.')]
    #[Assert\GreaterThan(0)]
    public string $amount;

    #[Assert\Length(min: 10, max: 2000)]
    public string $reason;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $sourceReference;

    public function __construct(
        string $customerCode,
        string $type,
        string $amount,
        string $reason,
        string $sourceReference,
    ) {
        $this->customerCode = trim($customerCode);
        $this->type = trim($type);
        $this->amount = trim($amount);
        $this->reason = trim($reason);
        $this->sourceReference = trim($sourceReference);
    }
}
