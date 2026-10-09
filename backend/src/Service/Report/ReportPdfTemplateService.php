<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Dto\Report\Output\ReportExportContext;
use App\Exception\Business\BusinessException;
use App\Service\Enterprise\GetEnterpriseLogoService;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ReportPdfTemplateService
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir, private GetEnterpriseLogoService $enterpriseLogo)
    {
    }

    private static function escapeLogo(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    public function render(ReportExportContext $context, iterable $rows): string
    {
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        $money = static fn (mixed $value): string => null === $value || '' === $value ? '—' : 'R$ '.number_format((float) $value, 2, ',', '.');
        $date = static fn (mixed $value): string => null === $value || '' === $value ? '—' : (new DateTimeImmutable((string) $value))->format('d/m/Y');
        $companyLogo = $this->enterpriseLogo->dataUri();
        $companyImage = null === $companyLogo ? '' : '<img style="float:right;width:110px;max-height:70px" alt="Logo da empresa" src="'.self::escapeLogo($companyLogo).'">';
        $logo = base64_encode(file_get_contents($this->projectDir.'/public/logo-gcom.png'));
        $commissions = 'commissions' === $context->kind;
        $columns = true === $commissions
            ? ['Comissão / Cliente / Pedidos', 'Modalidade / Situação', 'Bruto', 'Deduções', 'Líquido', 'Pago', 'Datas']
            : ['Cliente / Tipo', 'Referência / Motivo', 'Valor', 'Situação / Modalidade', 'Comissão', 'Datas'];
        $widths = true === $commissions ? [29, 16, 10, 10, 10, 10, 15] : [15, 30, 12, 15, 14, 14];
        $html = '<html><head><meta charset="UTF-8"><style>
            @page{margin:28px 26px 34px}body{font-family:DejaVu Sans;font-size:9px;color:#20242b}
            header{border-bottom:2px solid #098a14;padding-bottom:10px}img{width:138px}
            h1{font-size:16px;margin:6px 0}p{margin:4px 0;line-height:1.5}.meta{font-size:8px;color:#606061}
            table{border-collapse:collapse;width:100%;table-layout:fixed;margin-top:16px}
            th{text-align:left;background:#eef4f0;color:#23332a;font-size:8px}
            td,th{padding:8px 6px;border-bottom:1px solid #dde6df;vertical-align:top;word-wrap:break-word}
            tr{page-break-inside:avoid}thead{display:table-header-group}.number{text-align:right;white-space:nowrap}
            .sub{display:block;font-size:8px;color:#606061;margin-top:4px;line-height:1.5}
            .atg td{background:#fffbeb}.atg .mode{font-weight:bold;color:#92400e}.notes td{font-size:8px;padding-top:4px;color:#606061}
            </style></head><body><header>'.$companyImage.'<img alt="GCOM" src="data:image/png;base64,'.$logo.'"><h1>'.$escape($context->title).'</h1>
            <p class="meta">'.$escape($context->criteria).'</p>
            <p class="meta">Gerado em: '.$escape($context->generatedAt).'<br>Dados preservados em: '.$escape($context->savedAt ?? $context->generatedAt).'</p></header><table><colgroup>';
        foreach ($widths as $width) {
            $html .= '<col style="width:'.$width.'%">';
        }
        $html .= '</colgroup><thead><tr>';
        foreach ($columns as $index => $label) {
            $numeric = true === $commissions ? true === in_array($index, [2, 3, 4, 5], true) : 2 === $index;
            $html .= '<th'.(true === $numeric ? ' class="number"' : '').'>'.$escape($label).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        $count = 0;
        foreach ($rows as $row) {
            if (++$count > 1000) {
                throw new BusinessException('PDF limitado a 1.000 registros. Reduza o período ou exporte XLSX/CSV.');
            }

            $html .= 'ATG (Autoagenciamento)' === ($row['mode'] ?? '') ? '<tr class="atg">' : '<tr>';

            if (true === $commissions) {
                $html .= '<td><strong>'.$escape($row['customer_code']).' · '.$escape($row['customer_name']).'</strong><span class="sub">'.$escape($row['code']).'</span><span class="sub">'.$escape($row['orders']).'</span></td>';
                $html .= '<td><span class="mode">'.$escape($row['mode']).'</span><span class="sub">'.$escape($row['status']).'</span></td>';
                foreach (['gross_amount', 'deductions', 'net_amount', 'paid_amount'] as $key) {
                    $html .= '<td class="number">'.$escape($money($row[$key])).'</td>';
                }
                $html .= '<td>Cadastro: '.$escape($date($row['created_at'])).'<span class="sub">Pagamento: '.$escape($date($row['paid_at'])).'</span></td></tr>';
                $notes = [];

                if ('' !== (string) ($row['reference'] ?? '')) {
                    $notes[] = 'Winthor · Rotina 749 — Lançamento de contas a pagar: '.$row['reference'].' · '.$row['verification'];
                }

                if ('Sim' === ($row['manual_amount'] ?? '')) {
                    $notes[] = 'Valor pago informado manualmente'.('' !== (string) ($row['manual_reason'] ?? '') ? ': '.$row['manual_reason'] : '.');
                }

                if ('' !== (string) ($row['rejection_reason'] ?? '')) {
                    $notes[] = 'Reprovação: '.$row['rejection_reason'].' · '.$row['rejected_by'].' · '.$date($row['rejected_at']);
                }

                if ([] !== $notes) {
                    $html .= '<tr class="notes"><td colspan="7">'.$escape(implode(' · ', $notes)).'</td></tr>';
                }
            } else {
                $html .= '<td><strong>'.$escape($row['customer_code']).'</strong><span class="sub">'.$escape($row['type']).'</span></td>';
                $html .= '<td>'.$escape($row['source_reference']).'<span class="sub">'.$escape($row['reason']).'</span></td>';
                $html .= '<td class="number">'.$escape($money($row['amount'])).'</td>';
                $html .= '<td>'.$escape($row['state']).'<span class="sub">'.$escape($row['mode']).'</span></td>';
                $html .= '<td>'.$escape($row['commission_code']).'<span class="sub">'.$escape($row['commission_status']).'</span></td>';
                $html .= '<td>Cadastro: '.$escape($date($row['created_at'])).'<span class="sub">Abatimento: '.$escape($date($row['deducted_at'])).'</span><span class="sub">Pagamento: '.$escape($date($row['paid_at'])).'</span></td></tr>';
            }
        }
        $html .= '</tbody></table><p class="meta">'.$count.' registros. Deduzido significa vinculado à comissão; o status e a data de pagamento indicam sua liquidação.</p></body></html>';

        return $html;
    }
}
