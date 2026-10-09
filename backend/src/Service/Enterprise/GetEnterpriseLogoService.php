<?php

declare(strict_types=1);

namespace App\Service\Enterprise;

use App\Exception\Business\BusinessException;
use App\Repository\Enterprise\EnterpriseRepository;

final readonly class GetEnterpriseLogoService
{
    public function __construct(private EnterpriseRepository $repository, private EnterpriseLogoStorageService $storage)
    {
    }

    public function path(): string
    {
        $filename = $this->repository->current()?->getLogoFilename();

        if (null === $filename || false === is_file($this->storage->path($filename))) {
            throw new BusinessException('Logo não encontrada.', 404);
        }

        return $this->storage->path($filename);
    }

    public function dataUri(): ?string
    {
        $filename = $this->repository->current()?->getLogoFilename();

        if (null === $filename || false === is_file($this->storage->path($filename))) {
            return null;
        }

        $mime = true === str_ends_with($filename, '.png') ? 'image/png' : 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($this->storage->path($filename)));
    }
}
