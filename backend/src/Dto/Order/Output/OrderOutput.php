<?php

declare(strict_types=1);

namespace App\Dto\Order\Output;

use App\Entity\Order\OrderItem;
use App\Entity\Order\OrderSnapshot;
use JsonSerializable;

final readonly class OrderOutput implements JsonSerializable
{
    public ?int $id;

    public string $orderNumber;

    public ?string $invoiceNumber;

    public string $customerCode;

    public string $customerName;

    public ?string $authorCustomerCode;

    public ?string $authorCustomerName;

    public string $total;

    public string $capturedAt;

    public ?int $commissionId;

    public int $itemCount;

    public array $header;

    /** @var list<OrderItemOutput> */
    public array $items;

    public function __construct(OrderSnapshot $order, private bool $detail = false, ?int $historicalCommissionId = null)
    {
        $this->id = $order->getId();
        $this->orderNumber = $order->getOrderNumber();
        $this->customerCode = $order->getCustomerCode();
        $this->customerName = $order->getCustomerName();
        $header = $order->getHeader();
        $invoiceNumber = $header['NUMNOTA'] ?? $header['NUMCUPOM'] ?? null;
        $this->invoiceNumber = null === $invoiceNumber ? null : (string) $invoiceNumber;
        $this->authorCustomerCode = true === isset($header['CODCLI']) ? (string) $header['CODCLI'] : null;
        $this->authorCustomerName = $header['COMMISSION_FINAL_CUSTOMER_NAME'] ?? $header['CLIENTE'] ?? ($this->authorCustomerCode === $this->customerCode ? $this->customerName : null);

        $this->total = $order->getTotal();
        $this->capturedAt = $order->getCapturedAt()->format(\DATE_ATOM);
        $this->commissionId = $historicalCommissionId ?? $order->getCommission()?->getId();
        $this->itemCount = $order->getItems()->count();
        $this->header = true === $detail ? $order->getHeader() : [];
        $this->items = true === $detail ? array_map(static fn (OrderItem $item): OrderItemOutput => new OrderItemOutput($item), $order->getItems()->toArray()) : [];
    }

    public function jsonSerialize(): array
    {
        $data = get_object_vars($this);
        unset($data['detail']);

        if (false === $this->detail) {
            unset($data['header'], $data['items']);
        }

        return $data;
    }
}
