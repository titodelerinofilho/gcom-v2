<?php

declare(strict_types=1);

namespace App\Service\Report\Export;

use App\Dto\Report\Output\ReportExportContext;
use App\Service\Report\ReportPdfTemplateService;
use App\Service\Report\ReportRowsService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PdfExportStrategy implements ReportFormatStrategyInterface
{
    public function __construct(
        private ReportRowsService $rows,
        private ReportPdfTemplateService $template,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function write(ReportExportContext $context): void
    {
        $path = $context->path;
        $html = $this->template->render($context, $this->rows->rows($context));
        $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'tempDir' => sys_get_temp_dir(), 'fontCache' => sys_get_temp_dir(), 'chroot' => $this->projectDir.'/public']));
        $pdf->setPaper('A4', 'landscape');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();
        $pdf->getCanvas()->page_text(740, 565, '{PAGE_NUM} / {PAGE_COUNT}', null, 8);
        file_put_contents($path, $pdf->output());
    }
}
