<?php

declare(strict_types=1);

namespace App\Service\Report\Export;

use App\Dto\Report\Output\ReportExportContext;
use App\Exception\Business\BusinessException;
use App\Service\Report\ReportRowsService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PdfExportStrategy implements ReportFormatStrategyInterface
{
    private const MONEY_COLUMNS = ['gross_amount', 'deductions', 'net_amount', 'paid_amount', 'amount'];

    public function __construct(
        private ReportRowsService $rows,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function write(ReportExportContext $context): void
    {
        $path = $context->path;
        $columns = $context->columns;
        $kind = $context->kind;
        $title = $context->title;
        $criteria = $context->criteria;

        $escape = static fn ($value) => htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        $logo = base64_encode(file_get_contents($this->projectDir.'/public/logo-dts.png'));
        $html = '<html><head><meta charset="UTF-8"><style>@page{margin:24px}body{font-family:DejaVu Sans;font-size:7px;color:#23332a}header{border-bottom:2px solid #098a14}img{width:105px}h1{display:inline;color:#098a14;font-size:23px}table{border-collapse:collapse;width:100%;table-layout:fixed;margin-top:16px}th{background:#098a14;color:white}td,th{padding:4px;border-bottom:1px solid #dde6df;overflow-wrap:break-word}tr{page-break-inside:avoid}thead{display:table-header-group}</style></head><body><header><img src="data:image/png;base64,'.$logo.'"><h1>GCOM</h1><p>'.$escape($title).' · '.$escape($criteria).'</p></header><table><thead><tr>';
        foreach ($columns as $label) {
            $html .= '<th>'.$escape($label).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        $count = 0;
        foreach ($this->rows->rows($context) as $row) {
            if (++$count > 1000) {
                throw new BusinessException('PDF limitado a 1.000 registros. Reduza o período ou exporte XLSX/CSV.');
            }
            $html .= '<tr>';
            foreach ($row as $key => $value) {
                $display = true === in_array($key, self::MONEY_COLUMNS, true) ? 'R$ '.number_format((float) $value, 2, ',', '.') : $value;
                $html .= '<td>'.$escape($display).'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table><p>'.$count.' registros. Deduzido significa vinculado à comissão; o status e a data de pagamento indicam sua liquidação.</p></body></html>';
        $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'tempDir' => sys_get_temp_dir(), 'fontCache' => sys_get_temp_dir(), 'chroot' => $this->projectDir.'/public']));
        $pdf->setPaper('A4', 'landscape');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();
        $pdf->getCanvas()->page_text(740, 565, '{PAGE_NUM} / {PAGE_COUNT}', null, 8);
        file_put_contents($path, $pdf->output());
    }
}
