<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Dto\Audit\Input\ListAuditInput;
use App\Dto\Audit\Output\AuditOutput;
use App\Dto\Audit\Output\ListAuditOutput;
use App\Entity\Audit\AuditEvent;
use App\Repository\Audit\AuditEventRepository;

final readonly class ListAuditService
{
    public function __construct(
        private AuditEventRepository $repository,
    ) {
    }

    public function list(ListAuditInput $input): ListAuditOutput
    {
        $page = $this->repository->findPage($input->action, $input->subject, $input->page);
        $items = array_map(static fn (AuditEvent $item): AuditOutput => new AuditOutput($item), $page['items']);

        return new ListAuditOutput($items, $page['total'], $input->page);
    }
}
