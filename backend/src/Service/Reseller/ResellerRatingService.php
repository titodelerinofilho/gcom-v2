<?php

declare(strict_types=1);

namespace App\Service\Reseller;

use App\Dto\Reseller\Output\ActivityOutput;
use App\Dto\Reseller\Output\DebtSummaryOutput;
use App\Dto\Reseller\Output\RatingComponentOutput;
use App\Dto\Reseller\Output\RatingOutput;

final readonly class ResellerRatingService
{
    public function rate(ActivityOutput $activity, DebtSummaryOutput $debts, int $days): RatingOutput
    {
        $sales = max(0.0, (float) $activity->salesAmount);
        $annualTarget = max(1.0, 60 * $days / 365);
        $minimumSample = max(3, (int) ceil(10 * $days / 365));
        $cancellationRate = $activity->cancelledOrders / max(1, $activity->soldOrders + $activity->cancelledOrders);
        $returnRate = 0.0 < $sales ? (float) $activity->returnedAmount / $sales : (0.0 < (float) $activity->returnedAmount ? 1.0 : 0.0);
        $debtRate = 0.0 < $sales ? (float) $debts->overdueAmount / $sales : (0.0 < (float) $debts->overdueAmount ? 1.0 : 0.0);
        $volumePoints = (int) round(min(1.0, $activity->soldOrders / $annualTarget) * 40);
        $debtPoints = (int) round(30 * (1 - min(1.0, max(0.0, $debtRate) / 0.2)));
        $cancelPoints = (int) round(15 * (1 - min(1.0, $cancellationRate / 0.2)));
        $returnPoints = (int) round(15 * (1 - min(1.0, max(0.0, $returnRate) / 0.2)));
        $score = $volumePoints + $debtPoints + $cancelPoints + $returnPoints;
        $tier = 80 <= $score ? 'gold' : (55 <= $score ? 'silver' : 'bronze');
        $confidence = $activity->soldOrders < $minimumSample ? 'Histórico reduzido' : 'Histórico suficiente';

        if ($activity->soldOrders < $minimumSample || 60 <= $debts->maxLateDays) {
            $tier = 'bronze';
        }

        if (0 === $activity->soldOrders) {
            $tier = 'unrated';
            $score = 0;
            $volumePoints = 0;
            $debtPoints = 0;
            $cancelPoints = 0;
            $returnPoints = 0;
            $confidence = 'Sem vendas faturadas no período';
        }

        $label = ['gold' => 'Ouro', 'silver' => 'Prata', 'bronze' => 'Bronze', 'unrated' => 'Sem classificação'][$tier];
        $components = [
            new RatingComponentOutput('Vendas agenciadas', $volumePoints, 40, 'Meta proporcional de 60 pedidos por ano; '.$activity->soldOrders.' pedidos no período.'),
            new RatingComponentOutput('Pontualidade da carteira', $debtPoints, 30, 'Débitos vencidos atuais / vendas do período. Penalidade máxima a partir de 20%; atraso de 60 dias limita a Bronze.'),
            new RatingComponentOutput('Cancelamentos', $cancelPoints, 15, 'Pedidos cancelados / (faturados + cancelados). Penalidade máxima a partir de 20%.'),
            new RatingComponentOutput('Devoluções', $returnPoints, 15, 'Mercadorias devolvidas / vendas faturadas. Penalidade máxima a partir de 20%.'),
        ];

        return new RatingOutput($tier, $label, $score, $confidence, 0 < $activity->soldOrders + $activity->cancelledOrders ? self::percentage($cancellationRate) : null, 0.0 < $sales ? self::percentage($returnRate) : null, 0.0 < $sales ? self::percentage($debtRate) : null, $components);
    }

    private static function percentage(float $ratio): string
    {
        return number_format(max(0.0, $ratio) * 100, 1, '.', '');
    }
}
