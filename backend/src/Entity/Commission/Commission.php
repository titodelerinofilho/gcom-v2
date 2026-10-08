<?php

declare(strict_types=1);

namespace App\Entity\Commission;

use App\Entity\Order\OrderSnapshot;
use App\Entity\User\User;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Commission\CommissionRepository::class)]
#[ORM\Table(name: 'commission')]
#[ORM\Index(name: 'commission_created_status_idx', columns: ['created_at', 'status'])]
class Commission
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\Column(length: 40, unique: true)]
    private string $code;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
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

    #[ORM\Column(length: 200)]
    private string $customerName = '';

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): self
    {
        $this->customerName = $customerName;

        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $grossAmount = '0.00';

    public function getGrossAmount(): string
    {
        return $this->grossAmount;
    }

    public function setGrossAmount(string $grossAmount): self
    {
        $this->grossAmount = $grossAmount;

        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $deductions = '0.00';

    public function getDeductions(): string
    {
        return $this->deductions;
    }

    public function setDeductions(string $deductions): self
    {
        $this->deductions = $deductions;

        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $netAmount = '0.00';

    public function getNetAmount(): string
    {
        return $this->netAmount;
    }

    public function setNetAmount(string $netAmount): self
    {
        $this->netAmount = $netAmount;

        return $this;
    }

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    #[ORM\Column(type: 'json')]
    private array $calculation = [];

    public function getCalculation(): array
    {
        return $this->calculation;
    }

    public function setCalculation(array $calculation): self
    {
        $this->calculation = $calculation;

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

    #[ORM\ManyToOne]
    private ?User $approvedBy = null;

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): self
    {
        $this->approvedBy = $approvedBy;

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

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $approvedAt = null;

    public function getApprovedAt(): ?DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?DateTimeImmutable $approvedAt): self
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    #[ORM\OneToMany(mappedBy: 'commission', targetEntity: OrderSnapshot::class)]
    private Collection $orders;

    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function setOrders(Collection $orders): self
    {
        $this->orders = $orders;

        return $this;
    }

    public function __construct()
    {
        $this->code = 'DTS-'.strtoupper(bin2hex(random_bytes(8)));
        $this->createdAt = new DateTimeImmutable();
        $this->orders = new ArrayCollection();
    }

    public function addOrder(OrderSnapshot $order): void
    {
        $order->assignCommission($this);
        $this->orders->add($order);
    }
}
