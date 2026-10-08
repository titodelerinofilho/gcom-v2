<?php

declare(strict_types=1);

namespace App\Controller\Report;

use App\Entity\User\User;
use App\Service\Report\ExportSavedReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/reports/history/{id}.{format}', methods: ['GET'], requirements: ['id' => '[a-f0-9]{32}', 'format' => 'pdf|xlsx|csv'])]
final class ExportSavedReportController extends AbstractController
{
    public function __construct(private readonly ExportSavedReportService $service)
    {
    }

    public function __invoke(string $id, string $format, #[CurrentUser] User $actor): BinaryFileResponse
    {
        $output = $this->service->export($id, $format, $actor);
        $response = new BinaryFileResponse($output->path, headers: ['Content-Type' => $output->contentType, 'Cache-Control' => 'private, no-store']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $output->filename);
        $response->deleteFileAfterSend();

        return $response;
    }
}
