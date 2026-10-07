<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\OrderItemRepository::class)]
#[ORM\Table(name: 'order_item')]
class OrderItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\ManyToOne(inversedBy: 'items'), ORM\JoinColumn(nullable: false)]
    private OrderSnapshot $order;

    public function getOrder(): OrderSnapshot
    {
        return $this->order;
    }

    #[ORM\Column(length: 30)]
    private string $productCode;

    public function getProductCode(): string
    {
        return $this->productCode;
    }

    #[ORM\Column(length: 255)]
    private string $description;

    public function getDescription(): string
    {
        return $this->description;
    }

    #[ORM\Column(type: 'decimal', precision: 18, scale: 6)]
    private string $quantity;

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    #[ORM\Column(type: 'decimal', precision: 18, scale: 6)]
    private string $unitPrice;

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    #[ORM\Column(type: 'decimal', precision: 18, scale: 6)]
    private string $referencePrice;

    public function getReferencePrice(): string
    {
        return $this->referencePrice;
    }

    #[ORM\Column(type: 'json')]
    private array $raw;

    public function getRaw(): array
    {
        return $this->raw;
    }

    public function __construct(OrderSnapshot $order, array $item)
    {
        $this->order = $order;
        $this->productCode = (string) $item['CODPROD'];
        $this->description = (string) $item['DESCRICAO'];
        $this->quantity = \App\Service\Money::decimal((string) $item['QT'], 6);
        $this->unitPrice = \App\Service\Money::decimal((string) $item['PVENDA'], 6);
        $this->referencePrice = \App\Service\Money::decimal((string) ($item['PTABELA'] ?? '0'), 6);
        $this->raw = $item;
    }
}
