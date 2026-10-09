<?php

declare(strict_types=1);

namespace App\Service\Enterprise;

use App\Exception\Business\BusinessException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class EnterpriseLogoStorageService
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/enterprise-logos/%kernel.environment%')] private string $directory)
    {
    }

    public function store(UploadedFile $file): string
    {
        if (false === is_dir($this->directory) && false === mkdir($this->directory, 0750, true) && false === is_dir($this->directory)) {
            throw new BusinessException('Não foi possível preparar o armazenamento da logo.', 503);
        }

        $extension = match ($file->getMimeType()) {
            'image/png' => 'png', 'image/jpeg' => 'jpg', default => throw new BusinessException('Envie uma imagem PNG ou JPEG.', 422),
        };
        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($this->directory, $filename);
        chmod($this->path($filename), 0640);

        return $filename;
    }

    public function path(string $filename): string
    {
        if (1 !== preg_match('/^[a-f0-9]{32}\.(png|jpg)$/D', $filename)) {
            throw new BusinessException('Logo não encontrada.', 404);
        }

        return $this->directory.'/'.$filename;
    }

    public function remove(?string $filename): void
    {
        if (null !== $filename && true === is_file($this->path($filename))) {
            unlink($this->path($filename));
        }
    }
}
