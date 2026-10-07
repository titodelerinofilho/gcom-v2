<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\BusinessException;
use App\Repository\ReportRepository;
use DateTimeImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

final class ReportExporter
{
    private const COLUMNS = [
        'commissions' => ['code' => 'Código', 'customer_code' => 'Cliente', 'customer_name' => 'Nome', 'gross_amount' => 'Bruto', 'deductions' => 'Deduções', 'net_amount' => 'Líquido calculado', 'paid_amount' => 'Valor pago', 'manual_amount' => 'Valor manual', 'manual_reason' => 'Justificativa manual', 'status' => 'Status', 'created_at' => 'Criada em', 'routine' => 'Rotina', 'reference' => 'RECNUM PCLANC', 'verification' => 'Verificação', 'paid_at' => 'Pago em', 'mode' => 'Modalidade', 'orders' => 'Pedidos'],
        'adjustments' => ['customer_code' => 'Cliente principal', 'type' => 'Tipo', 'source_reference' => 'Referência', 'reason' => 'Motivo', 'amount' => 'Valor', 'state' => 'Situação', 'created_at' => 'Lançado em', 'commission_code' => 'Comissão do abatimento', 'deducted_at' => 'Deduzido em', 'commission_status' => 'Status da comissão', 'paid_at' => 'Pago em', 'mode' => 'Modalidade'],
    ];

    private const MONEY_COLUMNS = ['gross_amount', 'deductions', 'net_amount', 'paid_amount', 'amount'];

    public function __construct(private ReportRepository $reports, #[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
    }

    public function generate(string $format, string $from, string $to, array $filters = [], string $kind = 'commissions'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gcom-report-');

        if (false === $path) {
            throw new BusinessException('Não foi possível gerar o arquivo.', 503);
        }

        try {
            $columns = self::COLUMNS[$kind];
            $title = 'commissions' === $kind ? 'Comissões' : 'Débitos, devoluções e cancelamentos';
            $end = (new DateTimeImmutable($to))->modify('-1 day')->format('Y-m-d');
            $criteria = $from.' a '.$end;
            $labels = ['customer' => 'Cliente principal', 'orderNumber' => 'Pedido', 'status' => 'Status da comissão', 'mode' => 'Modalidade', 'type' => 'Tipo', 'state' => 'Abatimento', 'dateBasis' => 'Data considerada'];
            $values = ['normal' => 'Normal', 'atg' => 'ATG', 'pending' => 'Pendente', 'approved' => 'Aprovada', 'paid' => 'Paga / pagamento', 'deducted' => 'Deduzido', 'debt' => 'Débito', 'return' => 'Devolução', 'cancellation' => 'Cancelamento', 'created' => 'Cadastro', 'applied' => 'Abatimento'];
            foreach ($filters as $key => $value) {
                $criteria .= ' · '.$labels[$key].': '.($values[$value] ?? $value);
            }
            match ($format) {
                'csv' => $this->csv($path, $from, $to, $filters, $kind, $columns),
                'xlsx' => $this->xlsx($path, $from, $to, $filters, $kind, $columns, $title, $criteria),
                'pdf' => $this->pdf($path, $from, $to, $filters, $kind, $columns, $title, $criteria),
            };

            return $path;
        } catch (Throwable $e) {
            unlink($path);

            throw $e;
        }
    }

    private function rows(string $from, string $to, array $filters, string $kind, array $columns): iterable
    {
        foreach ($this->reports->export($from, $to, $filters, $kind) as $source) {
            $row = [];
            foreach ($columns as $key => $label) {
                $row[$key] = $source[$key] ?? '';
            }

            yield $row;
        }
    }

    private function csv(string $path, string $from, string $to, array $filters, string $kind, array $columns): void
    {
        $out = fopen($path, 'w');

        try {
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($columns), ';', '"', '');
            foreach ($this->rows($from, $to, $filters, $kind, $columns) as $row) {
                $values = array_map(static fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : $v, array_values($row));
                fputcsv($out, $values, ';', '"', '');
            }
        } finally {
            fclose($out);
        }
    }

    private function xlsx(string $path, string $from, string $to, array $filters, string $kind, array $columns, string $title, string $criteria): void
    {
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
            foreach ($this->rows($from, $to, $filters, $kind, $columns) as $row) {
                $cells = [];
                foreach ($row as $key => $value) {
                    $cells[] = in_array($key, self::MONEY_COLUMNS, true) ? new NumericCell((float) $value, $currency) : new StringCell((string) $value, null);
                }
                $writer->addRow(new Row($cells));
            }
        } finally {
            $writer->close();
        }
    }

    private function pdf(string $path, string $from, string $to, array $filters, string $kind, array $columns, string $title, string $criteria): void
    {
        $escape = static fn ($v) => htmlspecialchars((string) $v, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        $logo = base64_encode(file_get_contents($this->projectDir.'/public/logo-dts.png'));
        $html = '<html><head><meta charset="UTF-8"><style>@page{margin:24px}body{font-family:DejaVu Sans;font-size:7px;color:#23332a}header{border-bottom:2px solid #098a14}img{width:105px}h1{display:inline;color:#098a14;font-size:23px}table{border-collapse:collapse;width:100%;table-layout:fixed;margin-top:16px}th{background:#098a14;color:white}td,th{padding:4px;border-bottom:1px solid #dde6df;overflow-wrap:break-word}tr{page-break-inside:avoid}thead{display:table-header-group}</style></head><body><header><img src="data:image/png;base64,'.$logo.'"><h1>GCOM</h1><p>'.$escape($title).' · '.$escape($criteria).'</p></header><table><thead><tr>';
        foreach ($columns as $label) {
            $html .= '<th>'.$escape($label).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        $count = 0;
        foreach ($this->rows($from, $to, $filters, $kind, $columns) as $row) {
            if (++$count > 1000) {
                throw new BusinessException('PDF limitado a 1.000 registros. Reduza o período ou exporte XLSX/CSV.');
            }
            $html .= '<tr>';
            foreach ($row as $key => $value) {
                $display = in_array($key, self::MONEY_COLUMNS, true) ? 'R$ '.number_format((float) $value, 2, ',', '.') : $value;
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
