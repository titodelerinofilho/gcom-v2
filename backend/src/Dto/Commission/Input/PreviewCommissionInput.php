<?php

declare(strict_types=1);

namespace App\Dto\Commission\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PreviewCommissionInput
{
    #[Assert\Count(min: 1, max: 100)]
    #[Assert\Type('list')]
    #[Assert\Unique]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $orderIds;

    #[Assert\Type('list')]
    #[Assert\Unique]
    #[Assert\Count(max: 100)]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $adjustmentIds;

    #[Assert\Choice(choices: ['normal', 'atg'])]
    public string $mode;

    public function __construct(
        array $orderIds,
        array $adjustmentIds = [],
        string $mode = 'normal',
    ) {
        $this->orderIds = $orderIds;
        $this->adjustmentIds = $adjustmentIds;
        $this->mode = trim($mode);
    }
}
