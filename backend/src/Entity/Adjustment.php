<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

#[ORM\Entity(repositoryClass: \App\Repository\AdjustmentRepository::class)]
#[ORM\Table(name: 'adjustment')]
#[ORM\Index(name: 'adjustment_customer_idx', columns: ['customer_code', 'commission_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_ADJUSTMENT_SOURCE', columns: ['source_key'])]
class Adjustment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\Column(length: 30)]
    private string $customerCode = '';

    public function getCustomerCode(): string
    {
        return $this->customerCode;
    }

    public function setCustomerCode(string $customerCode): self
    {
        $this->customerCode = $customerCode;

        return $this;
    }

    #[ORM\Column(length: 20)]
    private string $type = 'debt';

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $amount = '0.00';

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    #[ORM\Column(type: 'text')]
    private string $reason = '';

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    #[ORM\Column(length: 100)]
    private string $sourceReference = '';

    public function getSourceReference(): string
    {
        return $this->sourceReference;
    }

    public function setSourceReference(string $sourceReference): self
    {
        $this->sourceReference = $sourceReference;

        return $this;
    }

    #[ORM\ManyToOne]
    private ?Commission $commission = null;

    public function getCommission(): ?Commission
    {
        return $this->commission;
    }

    public function setCommission(?Commission $commission): self
    {
        $this->commission = $commission;

        return $this;
    }

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $sourceKey = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $sourceSnapshot = null;

    public function getSourceSnapshot(): ?array
    {
        return $this->sourceSnapshot;
    }

    public function captureSource(string $key, array $snapshot): self
    {
        if (null !== $this->sourceKey) {
            throw new LogicException('Source already captured.');
        }
        $this->sourceKey = $key;
        $this->sourceSnapshot = $snapshot;

        return $this;
    }

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
