<?php

declare(strict_types=1);

namespace App\Controller\Enterprise;

use App\Service\Enterprise\GetEnterpriseLogoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings/enterprise/logo', methods: ['GET'])]
final class GetEnterpriseLogoController extends AbstractController
{
    public function __construct(private readonly GetEnterpriseLogoService $service)
    {
    }

    public function __invoke(): BinaryFileResponse
    {
        return $this->file($this->service->path(), disposition: ResponseHeaderBag::DISPOSITION_INLINE);
    }
}
