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

    #[Assert\Regex(pattern: '/^[a-f0-9]{64}$/D')]
    public ?string $expectedChecksFingerprint;

    #[Assert\Type('list')]
    #[Assert\Unique]
    #[Assert\Count(max: 100)]
    #[Assert\All([new Assert\Type('string'), new Assert\Regex(pattern: '/^[1-9][0-9]{0,17}$/D')])]
    public ?array $returnTransactions;

    #[Assert\NotNull(message: 'Selecione a praça do pedido.')]
    #[Assert\Positive]
    public ?int $square;

    public function __construct(
        array $orderIds,
        array $adjustmentIds = [],
        string $mode = 'normal',
        ?string $reason = null,
        ?int $ruleVersion = null,
        ?array $expectedAdjustmentIds = null,
        ?string $expectedChecksFingerprint = null,
        ?array $returnTransactions = null,
        ?int $square = null,
    ) {
        $this->square = $square;
        $this->returnTransactions = $returnTransactions;
        $this->orderIds = $orderIds;
        $this->adjustmentIds = $adjustmentIds;
        $this->mode = trim($mode);
        $this->reason = trim($reason ?? '');
        $this->ruleVersion = $ruleVersion;
        $this->expectedAdjustmentIds = $expectedAdjustmentIds;
        $this->expectedChecksFingerprint = $expectedChecksFingerprint;
    }
}
