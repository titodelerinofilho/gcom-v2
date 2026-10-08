<?php

declare(strict_types=1);

namespace App\Dto\CommissionRule\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCommissionRuleInput
{
    #[Assert\Positive]
    public int $expectedVersion;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[0-9]{1,3}(?:\.[0-9]{1,4})?$/D')]
    #[Assert\GreaterThan(0)]
    #[Assert\LessThanOrEqual(100)]
    public string $percentage;

    #[Assert\Choice(choices: ['margin_psd', 'margin_table', 'sales'])]
    public string $basis;

    #[Assert\Count(min: 1, max: 200)]
    #[Assert\Type('list')]
    #[Assert\Unique(fields: ['branch', 'orderRegion'])]
    #[Assert\All([new Assert\Sequentially([
        new Assert\Type('array'),
        new Assert\Collection(fields: [
            'branch' => [new Assert\Type('string'), new Assert\Regex('/^(\*|[0-9]{1,10})$/D')],
            'orderRegion' => [new Assert\Type('integer'), new Assert\Range(min: 1, max: 999999)],
            'psdRegion' => [new Assert\Type('integer'), new Assert\Range(min: 1, max: 999999)],
            'pscfRegion' => [new Assert\Type('integer'), new Assert\Range(min: 1, max: 999999)],
        ]),
        new Assert\Expression('value["psdRegion"] != value["pscfRegion"]', message: 'As referências PSD e PSCF devem ser distintas.'),
    ])])]
    public array $priceContexts;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[0-9]{1,3}(?:\.[0-9]{1,4})?$/D')]
    #[Assert\GreaterThan(0)]
    #[Assert\LessThanOrEqual(100)]
    public string $atgPercentage;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[0-9]{1,3}(?:\.[0-9]{1,4})?$/D')]
    #[Assert\GreaterThan(0)]
    #[Assert\LessThanOrEqual(100)]
    public string $returnPercentage;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[0-9]{1,3}(?:\.[0-9]{1,4})?$/D')]
    #[Assert\GreaterThan(0)]
    #[Assert\LessThanOrEqual(100)]
    public string $atgReturnPercentage;

    public bool $subtractFreight;

    public bool $applyReferenceDiscount;

    #[Assert\Length(min: 10, max: 2000)]
    public string $reason;

    public function __construct(
        int $expectedVersion,
        string $percentage,
        string $basis,
        array $priceContexts,
        string $atgPercentage,
        string $returnPercentage,
        string $atgReturnPercentage,
        bool $subtractFreight,
        bool $applyReferenceDiscount,
        string $reason,
    ) {
        $this->expectedVersion = $expectedVersion;
        $this->percentage = trim($percentage);
        $this->basis = trim($basis);
        $this->priceContexts = $priceContexts;
        $this->atgPercentage = trim($atgPercentage);
        $this->returnPercentage = trim($returnPercentage);
        $this->atgReturnPercentage = trim($atgReturnPercentage);
        $this->subtractFreight = $subtractFreight;
        $this->applyReferenceDiscount = $applyReferenceDiscount;
        $this->reason = trim($reason);
    }
}
