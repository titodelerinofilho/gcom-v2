<?php

declare(strict_types=1);

namespace App\Dto\Commission\Input;

use App\Exception\BusinessException;
use App\Service\Input;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ConfirmPaymentInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{1,12}(?:\.\d{1,2})?$/D', message: 'Informe um valor positivo com até duas casas decimais e ponto.')]
        public string $amount,
        #[Assert\Date]
        public string $paidAt,
        #[Assert\Length(min: 10, max: 2000)]
        public string $notes,
        public bool $manualAmount = false,
        #[Assert\Length(max: 2000)]
        public ?string $manualReason = null,
        public ?string $recnum = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $manual = $data['manualAmount'] ?? false;

        if (false === is_bool($manual)) {
            throw new BusinessException('A opção de valor manual deve ser booleana.');
        }

        $reason = isset($data['manualReason']) ? Input::text($data, 'manualReason', 2000, 0) : null;

        if (true === $manual && (null === $reason || 10 > mb_strlen($reason))) {
            throw new BusinessException('Justifique a alteração manual com pelo menos 10 caracteres.');
        }

        if (false === $manual && null !== $reason && '' !== $reason) {
            throw new BusinessException('A justificativa manual exige a opção de valor manual.');
        }

        return new self(
            Input::text($data, 'amount', 20),
            Input::text($data, 'paidAt', 10),
            Input::text($data, 'notes', 2000, 10),
            $manual,
            true === $manual ? $reason : null,
            isset($data['recnum']) && '' !== $data['recnum'] ? Input::text($data, 'recnum', 18) : null,
        );
    }
}
