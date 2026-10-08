<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Dto\Report\Output\ReportExportContext;
use App\Exception\Business\BusinessException;
use App\Service\Report\Export\CsvExportStrategy;
use App\Service\Report\Export\PdfExportStrategy;
use App\Service\Report\Export\XlsxExportStrategy;
use DateTimeImmutable;
use Throwable;

final readonly class ReportExporterService
{
    private const array COLUMNS = [
        'commissions' => ['code' => 'Código', 'customer_code' => 'Cliente', 'customer_name' => 'Nome', 'gross_amount' => 'Bruto', 'deductions' => 'Deduções', 'net_amount' => 'Líquido calculado', 'paid_amount' => 'Valor pago', 'manual_amount' => 'Valor manual', 'manual_reason' => 'Justificativa manual', 'status' => 'Status', 'created_at' => 'Criada em', 'routine' => 'Rotina', 'reference' => 'RECNUM PCLANC', 'verification' => 'Verificação', 'paid_at' => 'Pago em', 'mode' => 'Modalidade', 'orders' => 'Pedidos'],
        'adjustments' => ['customer_code' => 'Cliente principal', 'type' => 'Tipo', 'source_reference' => 'Referência', 'reason' => 'Motivo', 'amount' => 'Valor', 'state' => 'Situação', 'created_at' => 'Lançado em', 'commission_code' => 'Comissão do abatimento', 'deducted_at' => 'Deduzido em', 'commission_status' => 'Status da comissão', 'paid_at' => 'Pago em', 'mode' => 'Modalidade'],
    ];

    public function __construct(
        private CsvExportStrategy $csv,
        private XlsxExportStrategy $xlsx,
        private PdfExportStrategy $pdf,
    ) {
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

            $context = new ReportExportContext(
                $path,
                $from,
                $to,
                $filters,
                $kind,
                $columns,
                $title,
                $criteria
            );

            $strategy = match ($format) {
                'csv' => $this->csv,
                'xlsx' => $this->xlsx,
                'pdf' => $this->pdf,
            };

            $strategy->write($context);

            return $path;
        } catch (Throwable $exception) {
            unlink($path);

            throw $exception;
        }
    }
}
