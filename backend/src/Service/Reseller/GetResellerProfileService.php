<?php

declare(strict_types=1);

namespace App\Service\Reseller;

use App\Dto\Reseller\Input\GetResellerProfileInput;
use App\Dto\Reseller\Output\ProfileMonthOutput;
use App\Dto\Reseller\Output\ResellerProfileOutput;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\ResellerGatewayInterface;
use App\Repository\Reseller\ResellerProfileRepository;
use DateTimeImmutable;
use DateTimeZone;

final readonly class GetResellerProfileService
{
    public function __construct(private ResellerGatewayInterface $oracle, private ResellerProfileRepository $finance, private ResellerRatingService $rating)
    {
    }

    public function get(GetResellerProfileInput $input, bool $export = false): ResellerProfileOutput
    {
        $from = new DateTimeImmutable($input->from);
        $to = new DateTimeImmutable($input->to);
        $days = (int) $from->diff($to)->days + 1;

        if ($from > $to || 366 < $days) {
            throw new BusinessException('Informe um período válido de até 366 dias.', 422);
        }

        $oracle = $this->oracle->get($input, $export);
        $finance = $this->finance->get($input, $export);
        $months = [];
        $last = $to->modify('first day of this month');
        for ($month = $from->modify('first day of this month'); $month <= $last; $month = $month->modify('+1 month')) {
            $months[$month->format('Y-m')] = ['orders' => 0, 'sales' => '0.00', 'generated' => '0.00', 'paid' => '0.00'];
        }
        foreach ($oracle->monthly as $month) {
            $months[$month->month]['orders'] = $month->orders;
            $months[$month->month]['sales'] = $month->salesAmount;
        }
        foreach ($finance->monthly as $month) {
            $months[$month->month]['generated'] = $month->generatedAmount;
            $months[$month->month]['paid'] = $month->paidAmount;
        }
        $monthly = [];
        foreach ($months as $key => $value) {
            $monthly[] = new ProfileMonthOutput($key, $value['orders'], $value['sales'], $value['generated'], $value['paid']);
        }

        if (true === $export && 1000 < $finance->paid->total + $finance->returns->total + $oracle->debts->total + $oracle->cancellations->total) {
            throw new BusinessException('PDF limitado a 1.000 registros nas listas. Reduza o período para exportar a ficha completa.', 422);
        }

        return new ResellerProfileOutput(
            $oracle->customer,
            $input->from,
            $input->to,
            new DateTimeImmutable('now', new DateTimeZone('America/Fortaleza'))->format(\DATE_ATOM),
            $oracle->activity,
            $finance->summary,
            $oracle->debtSummary,
            $this->rating->rate($oracle->activity, $oracle->debtSummary, $days),
            $monthly,
            $oracle->topCustomers,
            $finance->paid,
            $finance->returns,
            $oracle->debts,
            $oracle->cancellations,
            [
                'Vendas agenciadas: clientes atualmente vinculados por CODREVENDA, condições 1/7 e critérios de praça da comissão normal. Vendas próprias e ATG ficam fora dos indicadores e comissões desta ficha.',
                'Winthor: faturamento pela data do pedido e situação atual; devoluções pela data do movimento; cancelamentos pela data do cancelamento. Carteira e débitos refletem os vínculos atuais, não um cadastro histórico.',
                'GCOM: comissões geradas e devoluções abatidas pela criação da comissão; pagamentos pela data de pagamento e valor efetivamente confirmado. Comissões reprovadas e abatimentos liberados não entram.',
                'Débitos são títulos em aberto consultados agora, inclusive a vencer, independentemente do período. Dias em atraso seguem o calendário e a previsão de recebimento do Winthor.',
                'Mercadorias devolvidas e canceladas são valores de venda; abatimentos são valores de comissão preservados no GCOM e não são recalculados pela tabela atual.',
                'Rating revenda-v1: Ouro ≥ 80; Prata ≥ 55; Bronze abaixo de 55. Amostra menor que 10 pedidos/ano (proporcional, mínimo 3) ou atraso ≥ 60 dias limita a Bronze. Sem vendas, sem classificação. Indicador gerencial, sem bloquear operações.',
            ],
        );
    }
}
