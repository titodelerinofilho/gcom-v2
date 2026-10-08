<?php

declare(strict_types=1);

namespace App\Dto\Commission\Output;

use App\Dto\Adjustment\Output\AdjustmentOutput;
use App\Dto\Order\Output\OrderOutput;
use App\Entity\Commission\Commission;
use App\Entity\Order\OrderSnapshot;
use JsonSerializable;

final readonly class CommissionOutput implements JsonSerializable
{
    public ?int $id;

    public string $code;

    public string $customerCode;

    public string $customerName;

    public string $grossAmount;

    public string $deductions;

    public string $netAmount;

    public string $mode;

    public string $status;

    public string $createdAt;

    public CommissionActorOutput $createdBy;

    public ?string $approvedBy;

    public ?string $approvedAt;

    public array $calculation;

    /** @var list<OrderOutput> */
    public array $orders;

    /** @param list<AdjustmentOutput> $adjustments */
    public function __construct(
        Commission $commission,
        private bool $detail = false,
        public ?PaymentOutput $payment = null,
        public array $adjustments = [],
        private bool $includePayment = false,
        ?array $calculation = null,
    ) {
        $this->id = $commission->getId();
        $this->code = $commission->getCode();
        $this->customerCode = $commission->getCustomerCode();
        $this->customerName = $commission->getCustomerName();
        $this->grossAmount = $commission->getGrossAmount();
        $this->deductions = $commission->getDeductions();
        $this->netAmount = $commission->getNetAmount();
        $this->mode = $commission->getCalculation()['mode'] ?? 'normal';
        $this->status = $commission->getStatus();
        $this->createdAt = $commission->getCreatedAt()->format(\DATE_ATOM);
        $this->createdBy = new CommissionActorOutput($commission->getCreatedBy());
        $this->approvedBy = $commission->getApprovedBy()?->getName();
        $this->approvedAt = $commission->getApprovedAt()?->format(\DATE_ATOM);
        $this->calculation = true === $detail ? ($calculation ?? $commission->getCalculation()) : [];
        $this->orders = true === $detail ? array_map(static fn (OrderSnapshot $order): OrderOutput => new OrderOutput($order, true), $commission->getOrders()->toArray()) : [];
    }

    public function jsonSerialize(): array
    {
        $data = get_object_vars($this);
        unset($data['detail'], $data['includePayment']);

        if (false === $this->detail) {
            unset($data['calculation'], $data['orders']);
        }

        if (false === $this->includePayment) {
            unset($data['payment'], $data['adjustments']);
        }

        return $data;
    }
}
