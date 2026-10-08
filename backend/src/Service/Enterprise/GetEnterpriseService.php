<?php

declare(strict_types=1);

namespace App\Service\Enterprise;

use App\Dto\Enterprise\Output\EnterpriseOutput;
use App\Repository\Enterprise\EnterpriseRepository;

final readonly class GetEnterpriseService
{
    public function __construct(private EnterpriseRepository $repository)
    {
    }

    public function get(): EnterpriseOutput
    {
        return new EnterpriseOutput($this->repository->current());
    }
}
