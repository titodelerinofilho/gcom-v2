<?php

declare(strict_types=1);

namespace App\Service\Enterprise;

use App\Dto\Enterprise\Input\UpdateEnterpriseInput;
use App\Dto\Enterprise\Output\EnterpriseOutput;
use App\Entity\Enterprise\Enterprise;
use App\Entity\User\User;
use App\Repository\Enterprise\EnterpriseRepository;
use App\Service\Audit\AuditRecorderService;

final readonly class UpdateEnterpriseService
{
    public function __construct(private EnterpriseRepository $repository, private AuditRecorderService $audit)
    {
    }

    public function update(UpdateEnterpriseInput $input, ?User $actor, bool $onlyIfMissing = false): EnterpriseOutput
    {
        return $this->repository->save(function () use ($input, $actor, $onlyIfMissing) {
            $enterprise = $this->repository->current();

            if (true === $onlyIfMissing && null !== $enterprise) {
                return new EnterpriseOutput($enterprise);
            }

            $previous = new EnterpriseOutput($enterprise);
            $enterprise ??= new Enterprise();
            $enterprise->update($input);
            $this->repository->store($enterprise);
            $output = new EnterpriseOutput($enterprise);
            $this->audit->record($actor, 'enterprise.updated', 'enterprise:1', ['previous' => $previous, 'current' => $output]);

            return $output;
        });
    }
}
