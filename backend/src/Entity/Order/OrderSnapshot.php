<?php

declare(strict_types=1);

namespace App\Entity\Order;

use App\Entity\Commission\Commission;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Order\OrderSnapshotRepository::class)]
#[ORM\Table(name: 'order_snapshot')]
class OrderSnapshot
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\Column(length: 30, unique: true)]
    private string $orderNumber;

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    #[ORM\Column(length: 30)]
    private string $customerCode;

    public function getCustomerCode(): string
    {
        return $this->customerCode;
    }

    #[ORM\Column(length: 200)]
    private string $customerName;

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $total;

    public function getTotal(): string
    {
        return $this->total;
    }

    #[ORM\Column]
    private DateTimeImmutable $capturedAt;

    public function getCapturedAt(): DateTimeImmutable
    {
        return $this->capturedAt;
    }

    #[ORM\Column(type: 'json')]
    private array $header;

    public function getHeader(): array
    {
        return $this->header;
    }

    #[ORM\ManyToOne(inversedBy: 'orders'), ORM\JoinColumn(nullable: true)]
    private ?Commission $commission = null;

    public function getCommission(): ?Commission
    {
        return $this->commission;
    }

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist'])]
    private Collection $items;

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function __construct(array $header, string $customerName, array $items)
    {
        $this->orderNumber = (string) $header['NUMPED'];
        $this->customerCode = (string) ($header['COMMISSION_PRINCIPAL'] ?? $header['CODCLI']);
        $this->customerName = $customerName;
        $this->total = \App\Service\Finance\MoneyService::normalize((string) $header['VLTOTAL']);
        $this->header = $header;
        $this->capturedAt = new DateTimeImmutable();
        $this->items = new ArrayCollection();
        foreach ($items as $item) {
            $this->items->add(new OrderItem($this, $item));
        }
    }

    public function assignCommission(Commission $commission): void
    {
        if (null !== $this->commission) {
            throw new \App\Exception\Business\BusinessException('Pedido já vinculado a uma comissão.', 409);
        }

        $this->commission = $commission;
    }
}
