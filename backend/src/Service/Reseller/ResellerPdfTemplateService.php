<?php

declare(strict_types=1);

namespace App\Service\Reseller;

use App\Dto\Reseller\Output\ResellerProfileOutput;
use App\Service\Enterprise\GetEnterpriseLogoService;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ResellerPdfTemplateService
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir, private GetEnterpriseLogoService $enterpriseLogo)
    {
    }

    private static function escapeLogo(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    public function render(ResellerProfileOutput $profile, string $enterprise): string
    {
        $escape = self::escape(...);
        $money = self::money(...);
        $date = self::date(...);
        $companyLogo = $this->enterpriseLogo->dataUri();
        $companyImage = null === $companyLogo ? '' : '<img style="float:right;width:110px;max-height:70px" alt="Logo da empresa" src="'.self::escapeLogo($companyLogo).'">';
        $logo = base64_encode(file_get_contents($this->projectDir.'/public/logo-gcom.png'));
        $html = '<html><head><meta charset="UTF-8"><style>
            @page{margin:30px 30px 38px}body{font-family:DejaVu Sans;font-size:9px;color:#24322d}
            header{border-bottom:3px solid #098a14;padding-bottom:12px}header img{width:105px}
            h1{font-size:22px;margin:12px 0 3px}h2{font-size:13px;margin:18px 0 6px}h3{font-size:10px;margin:12px 0 5px}
            p{line-height:1.5;margin:4px 0}.muted{color:#596860;font-size:8px}.large{font-size:15px;font-weight:bold}
            .badge{font-weight:bold;border:1px solid #9b8463;padding:6px 12px;background:#f8f2e7}
            .cards td{background:#f2f6f3;border:4px solid white;padding:12px;width:33%}
            table{width:100%;border-collapse:collapse;table-layout:fixed}td,th{padding:7px 5px;vertical-align:top;word-wrap:break-word}
            .data td{border-bottom:1px solid #dce4df}.data th{background:#edf3ee;text-align:left;font-size:8px}
            thead{display:table-header-group}tr{page-break-inside:avoid}.number{text-align:right}.bar{background:#098a14;height:6px;margin:4px 0}
            .new-page{page-break-before:always}.sources{page-break-inside:avoid}.warning{background:#fff6e2;border-left:3px solid #c58a14;padding:10px}
            </style></head><body><header>'.$companyImage.'<img alt="GCOM" src="data:image/png;base64,'.$logo.'"><p class="muted">'.$escape($enterprise).' · RELACIONAMENTO COMERCIAL</p>
            <h1>Ficha do Cliente Revenda</h1><p class="large">'.$escape($profile->customer->name).'</p><p>Código '.$escape($profile->customer->code).' · '.$date($profile->from).' a '.$date($profile->to).'</p>
            <p class="muted">Consultado em '.$escape(new DateTimeImmutable($profile->generatedAt)->format('d/m/Y H:i:s P')).' · Débitos e vínculos atuais</p></header>
            <h2>Visão executiva <span class="badge">'.$escape($profile->rating->label).' · '.$profile->rating->score.'/100</span></h2>
            <p class="muted">'.$escape($profile->rating->confidence).' · '.$escape($profile->rating->version).'</p><table class="cards">';
        $metrics = [
            ['Vendas agenciadas faturadas', $money($profile->activity->salesAmount), $profile->activity->soldOrders.' pedidos'],
            ['Comissões pagas', $money($profile->finance->paidAmount), $profile->finance->paidCount.' pagamentos no período'],
            ['Comissões a pagar', $money($profile->finance->pendingAmount), 'Pendentes/aprovadas criadas no período'],
            ['Devoluções abatidas', $money($profile->finance->appliedReturnsAmount), $profile->appliedReturns->total.' abatimentos em comissão'],
            ['Volume cancelado', $money($profile->activity->cancelledAmount), $profile->activity->cancelledOrders.' pedidos cancelados'],
            ['Carteira vinculada', (string) $profile->activity->linkedCustomers, $profile->activity->activeCustomers.' clientes com vendas no período'],
        ];
        foreach ($metrics as $index => [$label, $value, $hint]) {
            if (0 === $index % 3) {
                $html .= '<tr>';
            }
            $html .= '<td>'.$escape($label).'<p class="large">'.$escape($value).'</p><span class="muted">'.$escape($hint).'</span></td>';

            if (2 === $index % 3) {
                $html .= '</tr>';
            }
        }
        $html .= '</table><h2>Exposição financeira atual</h2><p>Em aberto: <strong>'.$money($profile->debtSummary->openAmount).'</strong> · Vencido: <strong>'.$money($profile->debtSummary->overdueAmount).'</strong></p>
            <p>Revenda: '.$money($profile->debtSummary->ownOverdueAmount).' vencidos · Carteira vinculada: '.$money($profile->debtSummary->linkedOverdueAmount).' vencidos</p>
            <p class="muted">'.$profile->debtSummary->overdueTitles.' títulos vencidos; maior atraso: '.$profile->debtSummary->maxLateDays.' dias. Mercadorias devolvidas: '.$money($profile->activity->returnedAmount).'.</p>
            <h2>Composição da classificação</h2><table class="data"><thead><tr><th style="width:27%">Critério</th><th style="width:14%">Pontos</th><th>Como é calculado</th></tr></thead><tbody>';
        foreach ($profile->rating->components as $component) {
            $html .= '<tr><td>'.$escape($component->label).'<div class="bar" style="width:'.(100 * $component->points / $component->maximum).'%"></div></td><td>'.$component->points.'/'.$component->maximum.'</td><td class="muted">'.$escape($component->detail).'</td></tr>';
        }
        $html .= '</tbody></table><p class="muted">Cancelamentos: '.(null === $profile->rating->cancellationRate ? '—' : $profile->rating->cancellationRate.'%').' · Devoluções: '.(null === $profile->rating->returnRate ? '—' : $profile->rating->returnRate.'%').' · Vencidos/vendas: '.(null === $profile->rating->overdueRate ? '—' : $profile->rating->overdueRate.'%').'. Indicadores com bases e datas diferentes; não representam margem líquida.</p>
            <div class="new-page"><h2>Evolução mensal</h2><p class="muted">Vendas pelo mês do pedido; comissões geradas pelo mês de criação; pagamentos pelo mês do pagamento.</p><table class="data"><thead><tr><th style="width:15%">Mês</th><th style="width:10%">Pedidos</th><th>Vendas agenciadas</th><th>Comissões líquidas</th><th>Comissões pagas</th></tr></thead><tbody>';
        $maxSales = max(1.0, ...array_map(static fn ($month): float => (float) $month->salesAmount, $profile->monthly));
        foreach ($profile->monthly as $month) {
            $html .= '<tr><td>'.$escape($month->month).'</td><td>'.$month->orders.'</td><td>'.$money($month->salesAmount).'<div class="bar" style="width:'.(100 * (float) $month->salesAmount / $maxSales).'%"></div></td><td>'.$money($month->generatedAmount).'</td><td>'.$money($month->paidAmount).'</td></tr>';
        }
        $html .= '</tbody></table><h2>Clientes vinculados com maior volume</h2><table class="data"><thead><tr><th>Cliente</th><th style="width:15%">Pedidos</th><th style="width:25%">Vendas</th></tr></thead><tbody>';
        foreach ($profile->topCustomers as $customer) {
            $html .= '<tr><td>'.$escape($customer->code.' · '.$customer->name).'</td><td>'.$customer->orders.'</td><td class="number">'.$money($customer->salesAmount).'</td></tr>';
        }
        $html .= self::emptyRow(count($profile->topCustomers), 3).'</tbody></table></div><div class="new-page"><h2>Comissões pagas · '.$profile->paidCommissions->total.'</h2><table class="data"><thead><tr><th>Comissão</th><th style="width:18%">Pagamento</th><th>Bruto</th><th>Deduções</th><th>Pago</th></tr></thead><tbody>';
        foreach ($profile->paidCommissions->items as $commission) {
            $html .= '<tr><td>'.$escape($commission->code).(true === $commission->manualAmount ? '<p class="muted">Pagamento com valor manual</p>' : '').'</td><td>'.$date($commission->paidAt).'</td><td>'.$money($commission->grossAmount).'</td><td>'.$money($commission->deductions).'</td><td>'.$money($commission->paidAmount).'</td></tr>';
        }
        $html .= self::emptyRow($profile->paidCommissions->total, 5).'</tbody></table><h2>Devoluções abatidas · '.$profile->appliedReturns->total.'</h2><table class="data"><thead><tr><th>Devolução</th><th>Comissão</th><th style="width:18%">Abatida em</th><th style="width:20%">Valor abatido</th></tr></thead><tbody>';
        foreach ($profile->appliedReturns->items as $return) {
            $html .= '<tr><td>'.$escape($return->reference).'<p class="muted">'.$escape($return->reason).'</p></td><td>'.$escape($return->commissionCode).'<p class="muted">'.self::status($return->commissionStatus).'</p></td><td>'.$date($return->appliedAt).'</td><td>'.$money($return->amount).'</td></tr>';
        }
        $html .= self::emptyRow($profile->appliedReturns->total, 4).'</tbody></table><h2>Títulos em aberto · '.$profile->debts->total.'</h2><table class="data"><thead><tr><th style="width:30%">Cliente</th><th>NF / parcela</th><th>Vencimento</th><th style="width:12%">Atraso</th><th>Valor</th></tr></thead><tbody>';
        foreach ($profile->debts->items as $debt) {
            $html .= '<tr><td>'.$escape($debt->customerCode.' · '.$debt->customerName).'<p class="muted">'.(true === $debt->own ? 'Próprio revenda' : 'Cliente vinculado').'</p></td><td>'.$escape(($debt->invoice ?? '—').' / '.$debt->installment).'</td><td>'.$date($debt->dueDate).'</td><td>'.(0 < $debt->daysLate ? $debt->daysLate.' dias' : 'A vencer / em dia').'</td><td>'.$money($debt->amount).'</td></tr>';
        }
        $html .= self::emptyRow($profile->debts->total, 5).'</tbody></table><h2>Cancelamentos · '.$profile->cancellations->total.'</h2><table class="data"><thead><tr><th style="width:15%">Pedido</th><th>Cliente / motivo</th><th style="width:18%">Cancelado em</th><th style="width:20%">Valor</th></tr></thead><tbody>';
        foreach ($profile->cancellations->items as $cancellation) {
            $html .= '<tr><td>'.$escape($cancellation->orderNumber).'<p class="muted">'.('order' === $cancellation->source ? 'Pedido' : 'Fiscal').'</p></td><td>'.$escape($cancellation->customerCode.' · '.$cancellation->customerName).'<p class="muted">'.$escape($cancellation->reason).'</p></td><td>'.$date($cancellation->cancelledAt).'</td><td>'.$money($cancellation->amount).'</td></tr>';
        }
        $html .= self::emptyRow($profile->cancellations->total, 4).'</tbody></table></div><div class="sources"><h2>Critérios e fontes</h2>';
        foreach ($profile->notes as $note) {
            $html .= '<p class="muted">'.$escape($note).'</p>';
        }

        return $html.'</div></body></html>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function money(string $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    private static function date(?string $value): string
    {
        return null === $value ? '—' : new DateTimeImmutable($value)->format('d/m/Y');
    }

    private static function emptyRow(int $count, int $columns): string
    {
        return 0 === $count ? '<tr><td colspan="'.$columns.'">Nenhum registro nesta consulta.</td></tr>' : '';
    }

    private static function status(string $status): string
    {
        return ['paid' => 'Paga', 'approved' => 'Aprovada', 'pending' => 'Pendente'][$status] ?? $status;
    }
}
