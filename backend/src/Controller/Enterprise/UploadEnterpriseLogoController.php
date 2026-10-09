<?php

declare(strict_types=1);

namespace App\Controller\Enterprise;

use App\Entity\User\User;
use App\Service\Enterprise\UpdateEnterpriseLogoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/api/settings/enterprise/logo', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
final class UploadEnterpriseLogoController extends AbstractController
{
    public function __construct(private readonly UpdateEnterpriseLogoService $service)
    {
    }

    public function __invoke(#[MapUploadedFile(constraints: new Assert\Image(maxSize: '1Mi', mimeTypes: ['image/png', 'image/jpeg'], maxWidth: 4096, maxHeight: 4096, detectCorrupted: true), name: 'logo')] UploadedFile $logo, #[CurrentUser] User $actor): JsonResponse
    {
        return $this->json($this->service->update($logo, $actor));
    }
}
