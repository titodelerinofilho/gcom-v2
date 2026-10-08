<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Dto\Commission\Input\ListCommissionsInput;
use App\Dto\Commission\Output\CommissionOutput;
use App\Dto\Commission\Output\ListCommissionsOutput;
use App\Entity\Commission\Commission;
use App\Repository\Commission\CommissionRepository;

final readonly class ListCommissionsService
{
    public function __construct(
        private CommissionRepository $repository,
    ) {
    }

    public function list(ListCommissionsInput $input): ListCommissionsOutput
    {
        $page = $this->repository->findPage($input->status, $input->page);
        $items = array_map(static fn (Commission $item): CommissionOutput => new CommissionOutput($item), $page['items']);

        return new ListCommissionsOutput($items, $page['total'], $input->page);
    }
}
