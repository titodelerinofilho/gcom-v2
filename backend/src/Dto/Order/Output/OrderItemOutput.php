<?php

declare(strict_types=1);

namespace App\Dto\Order\Output;

use App\Entity\Order\OrderItem;

final readonly class OrderItemOutput
{
    public ?int $id;

    public string $productCode;

    public string $description;

    public string $quantity;

    public string $unitPrice;

    public string $referencePrice;

    public array $raw;

    public function __construct(OrderItem $item)
    {
        $this->id = $item->getId();
        $this->productCode = $item->getProductCode();
        $this->description = $item->getDescription();
        $this->quantity = $item->getQuantity();
        $this->unitPrice = $item->getUnitPrice();
        $this->referencePrice = $item->getReferencePrice();
        $this->raw = $item->getRaw();
    }
}
