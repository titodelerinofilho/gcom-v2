<?php

declare(strict_types=1);

namespace App\Dto\Commission\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCommissionInput
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

    #[Assert\Length(max: 2000)]
    public string $reason;

    public ?int $ruleVersion;

    #[Assert\Type('list')]
    #[Assert\Unique]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public ?array $expectedAdjustmentIds;

    public function __construct(
        array $orderIds,
        array $adjustmentIds = [],
        string $mode = 'normal',
        ?string $reason = null,
        ?int $ruleVersion = null,
        ?array $expectedAdjustmentIds = null,
    ) {
        $this->orderIds = $orderIds;
        $this->adjustmentIds = $adjustmentIds;
        $this->mode = trim($mode);
        $this->reason = trim($reason ?? '');
        $this->ruleVersion = $ruleVersion;
        $this->expectedAdjustmentIds = $expectedAdjustmentIds;
    }
}
