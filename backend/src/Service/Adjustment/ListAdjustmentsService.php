<?php

declare(strict_types=1);

namespace App\Service\Adjustment;

use App\Dto\Adjustment\Input\ListAdjustmentsInput;
use App\Dto\Adjustment\Output\AdjustmentOutput;
use App\Dto\Adjustment\Output\ListAdjustmentsOutput;
use App\Entity\Adjustment\Adjustment;
use App\Repository\Adjustment\AdjustmentRepository;

final readonly class ListAdjustmentsService
{
    public function __construct(private AdjustmentRepository $repository)
    {
    }

    public function list(ListAdjustmentsInput $input): ListAdjustmentsOutput
    {
        $adjustments = $this->repository->findPage($input->customer, $input->available, $input->page);
        $items = array_map(static fn (Adjustment $adjustment): AdjustmentOutput => new AdjustmentOutput($adjustment), $adjustments);
        $total = $this->repository->countMatching($input->customer, $input->available);

        return new ListAdjustmentsOutput($items, $total, $input->page);
    }
}
