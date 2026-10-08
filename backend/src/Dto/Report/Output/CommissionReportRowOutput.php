<?php

declare(strict_types=1);

namespace App\Dto\Report\Output;

final readonly class CommissionReportRowOutput
{
    public string $code;

    public string $customer_code;

    public string $customer_name;

    public string $gross_amount;

    public string $deductions;

    public string $net_amount;

    public string $status;

    public string $created_at;

    public ?string $routine;

    public ?string $reference;

    public ?string $verification;

    public ?string $paid_at;

    public ?string $paid_amount;

    public ?string $calculated_amount;

    public ?string $manual_reason;

    public ?string $manual_amount;

    public string $mode;

    public ?string $orders;

    public int $id;

    public function __construct(array $row)
    {
        $this->code = (string) $row['code'];
        $this->customer_code = (string) $row['customer_code'];
        $this->customer_name = (string) $row['customer_name'];
        $this->gross_amount = (string) $row['gross_amount'];
        $this->deductions = (string) $row['deductions'];
        $this->net_amount = (string) $row['net_amount'];
        $this->status = (string) $row['status'];
        $this->created_at = (string) $row['created_at'];
        $this->routine = null === $row['routine'] ? null : (string) $row['routine'];
        $this->reference = null === $row['reference'] ? null : (string) $row['reference'];
        $this->verification = null === $row['verification'] ? null : (string) $row['verification'];
        $this->paid_at = null === $row['paid_at'] ? null : (string) $row['paid_at'];
        $this->paid_amount = null === $row['paid_amount'] ? null : (string) $row['paid_amount'];
        $this->calculated_amount = null === $row['calculated_amount'] ? null : (string) $row['calculated_amount'];
        $this->manual_reason = null === $row['manual_reason'] ? null : (string) $row['manual_reason'];
        $this->manual_amount = null === $row['manual_amount'] ? null : (string) $row['manual_amount'];
        $this->mode = (string) $row['mode'];
        $this->orders = null === $row['orders'] ? null : (string) $row['orders'];
        $this->id = (int) $row['id'];
    }
}
