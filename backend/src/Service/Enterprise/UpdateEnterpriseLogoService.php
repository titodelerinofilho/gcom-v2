<?php

declare(strict_types=1);

namespace App\Service\Enterprise;

use App\Dto\Enterprise\Output\EnterpriseOutput;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Repository\Enterprise\EnterpriseRepository;
use App\Service\Audit\AuditRecorderService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

final readonly class UpdateEnterpriseLogoService
{
    public function __construct(private EnterpriseRepository $repository, private EnterpriseLogoStorageService $storage, private AuditRecorderService $audit)
    {
    }

    public function update(?UploadedFile $file, User $actor): EnterpriseOutput
    {
        $filename = null === $file ? null : $this->storage->store($file);
        $previous = null;

        try {
            $output = $this->repository->save(function () use ($filename, $actor, &$previous): EnterpriseOutput {
                $enterprise = $this->repository->current();

                if (null === $enterprise) {
                    throw new BusinessException('Salve os dados da empresa antes de enviar a logo.', 409);
                }

                $previous = $enterprise->getLogoFilename();
                $enterprise->setLogoFilename($filename);
                $this->repository->store($enterprise);
                $this->audit->record($actor, null === $filename ? 'enterprise.logo_removed' : 'enterprise.logo_uploaded', 'enterprise:1', ['previous' => $previous, 'filename' => $filename]);

                return new EnterpriseOutput($enterprise);
            });
        } catch (Throwable $exception) {
            $this->storage->remove($filename);

            throw $exception;
        }

        $this->storage->remove($previous);

        return $output;
    }
}
