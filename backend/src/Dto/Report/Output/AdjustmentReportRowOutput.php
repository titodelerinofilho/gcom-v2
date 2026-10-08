<?php

declare(strict_types=1);

namespace App\Dto\Report\Output;

final readonly class AdjustmentReportRowOutput
{
    public string $customer_code;

    public string $type;

    public string $source_reference;

    public string $reason;

    public string $amount;

    public string $state;

    public string $created_at;

    public ?string $commission_code;

    public ?string $deducted_at;

    public ?string $commission_status;

    public ?string $paid_at;

    public string $mode;

    public int $id;

    public ?int $commission_id;

    public function __construct(array $row)
    {
        $this->customer_code = (string) $row['customer_code'];
        $this->type = (string) $row['type'];
        $this->source_reference = (string) $row['source_reference'];
        $this->reason = (string) $row['reason'];
        $this->amount = (string) $row['amount'];
        $this->state = (string) $row['state'];
        $this->created_at = (string) $row['created_at'];
        $this->commission_code = null === $row['commission_code'] ? null : (string) $row['commission_code'];
        $this->deducted_at = null === $row['deducted_at'] ? null : (string) $row['deducted_at'];
        $this->commission_status = null === $row['commission_status'] ? null : (string) $row['commission_status'];
        $this->paid_at = null === $row['paid_at'] ? null : (string) $row['paid_at'];
        $this->mode = (string) $row['mode'];
        $this->id = (int) $row['id'];
        $this->commission_id = null === $row['commission_id'] ? null : (int) $row['commission_id'];
    }
}
