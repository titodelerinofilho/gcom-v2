<?php

declare(strict_types=1);

namespace App\Controller\Report;

use App\Dto\Report\Input\ExportReportInput;
use App\Entity\User\User;
use App\Service\Report\ExportReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/reports/{kind}.{format}', methods: ['GET'], requirements: ['kind' => 'commissions|adjustments', 'format' => 'csv|xlsx|pdf'])]
final class ExportReportController extends AbstractController
{
    public function __construct(private readonly ExportReportService $service)
    {
    }

    public function __invoke(
        string $kind,
        string $format,
        #[MapQueryString(validationFailedStatusCode: 422)]
        ExportReportInput $input,
        #[CurrentUser]
        User $actor,
    ): BinaryFileResponse {
        $output = $this->service->export($kind, $format, $input, $actor);
        $response = new BinaryFileResponse($output->path, headers: ['Content-Type' => $output->contentType, 'Cache-Control' => 'private, no-store']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $output->filename);
        $response->deleteFileAfterSend();

        return $response;
    }
}
