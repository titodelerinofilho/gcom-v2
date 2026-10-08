<?php

declare(strict_types=1);

namespace App\Service\Report\Export;

use App\Dto\Report\Output\ReportExportContext;
use App\Service\Report\ReportRowsService;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

final readonly class XlsxExportStrategy implements ReportFormatStrategyInterface
{
    private const MONEY_COLUMNS = ['gross_amount', 'deductions', 'net_amount', 'paid_amount', 'amount'];

    public function __construct(
        private ReportRowsService $rows,
    ) {
    }

    public function write(ReportExportContext $context): void
    {
        $path = $context->path;
        $columns = $context->columns;
        $kind = $context->kind;
        $title = $context->title;
        $criteria = $context->criteria;

        $writer = new Writer();
        $writer->getOptions()->setColumnWidthForRange(20, 1, count($columns));
        $writer->openToFile($path);

        try {
            $writer->setCreator('GCOM · DTS');
            $writer->getCurrentSheet()->setName('commissions' === $kind ? 'Comissões' : 'Deduções');
            $style = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('098A14');
            $writer->addRow(new Row([new StringCell('GCOM · DTS — '.$title, $style), new StringCell($criteria, $style)]));
            $writer->addRow(Row::fromValues(array_values($columns), $style));
            $currency = (new Style())->setFormat('"R$" #,##0.00');
            foreach ($this->rows->rows($context) as $row) {
                $atgStyle = 'ATG (Autoagenciamento)' === ($row['mode'] ?? '') ? (new Style())->setBackgroundColor('FFFBEB')->setFontBold()->setFontColor('92400E') : null;
                $cells = [];
                foreach ($row as $key => $value) {
                    $cells[] = true === in_array($key, self::MONEY_COLUMNS, true) ? new NumericCell((float) $value, null === $atgStyle ? $currency : (clone $atgStyle)->setFormat('"R$" #,##0.00')) : new StringCell((string) $value, $atgStyle);
                }
                $writer->addRow(new Row($cells));
            }
        } finally {
            $writer->close();
        }
    }
}
