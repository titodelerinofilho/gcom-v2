<?php

declare(strict_types=1);

namespace App\Controller\Reseller;

use App\Dto\Reseller\Input\GetResellerProfileInput;
use App\Entity\User\User;
use App\Service\Reseller\ExportResellerProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/resellers/profile.pdf', methods: ['GET'])]
final class ExportResellerProfileController extends AbstractController
{
    public function __construct(private readonly ExportResellerProfileService $service)
    {
    }

    public function __invoke(#[MapQueryString(validationFailedStatusCode: 422)] GetResellerProfileInput $input, #[CurrentUser] User $actor): BinaryFileResponse
    {
        $output = $this->service->export($input, $actor);
        $response = new BinaryFileResponse($output->path, headers: ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $output->filename);
        $response->deleteFileAfterSend();

        return $response;
    }
}
