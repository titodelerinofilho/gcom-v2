<?php

declare(strict_types=1);

namespace App\Dto\CommissionRule\Output;

final readonly class CommissionRuleOutput
{
    public ?int $version;

    public string $percentage;

    public string $basis;

    public array $priceContexts;

    public string $atgPercentage;

    public string $returnPercentage;

    public string $atgReturnPercentage;

    public bool $subtractFreight;

    public bool $applyReferenceDiscount;

    public string $reason;

    public string $createdAt;

    public ?string $createdBy;

    public function __construct(array $rule)
    {
        $this->version = $rule['version'];
        $this->percentage = $rule['percentage'];
        $this->basis = $rule['basis'];
        $this->priceContexts = $rule['priceContexts'];
        $this->atgPercentage = $rule['atgPercentage'];
        $this->returnPercentage = $rule['returnPercentage'];
        $this->atgReturnPercentage = $rule['atgReturnPercentage'];
        $this->subtractFreight = $rule['subtractFreight'];
        $this->applyReferenceDiscount = $rule['applyReferenceDiscount'];
        $this->reason = $rule['reason'];
        $this->createdAt = $rule['createdAt'];
        $this->createdBy = $rule['createdBy'];
    }
}
