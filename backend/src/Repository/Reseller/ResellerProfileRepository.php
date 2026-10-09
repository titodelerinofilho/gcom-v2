<?php

declare(strict_types=1);

namespace App\Repository\Reseller;

use App\Dto\Reseller\Input\GetResellerProfileInput;
use App\Dto\Reseller\Output\AppliedReturnOutput;
use App\Dto\Reseller\Output\AppliedReturnsOutput;
use App\Dto\Reseller\Output\CommissionMonthOutput;
use App\Dto\Reseller\Output\FinanceSummaryOutput;
use App\Dto\Reseller\Output\PaidCommissionOutput;
use App\Dto\Reseller\Output\PaidCommissionsOutput;
use App\Dto\Reseller\Output\ResellerFinanceOutput;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class ResellerProfileRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function get(GetResellerProfileInput $input, bool $export = false): ResellerFinanceOutput
    {
        // Repeatable read keeps local summaries and pages consistent within one response.
        return $this->connection->transactional(function () use ($input, $export): ResellerFinanceOutput {
            $this->connection->executeStatement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            $params = ['customer' => $input->customer, 'from' => $input->from, 'to' => new DateTimeImmutable($input->to)->modify('+1 day')->format('Y-m-d')];
            $base = "c.customer_code = :customer AND c.status <> 'rejected' AND COALESCE(c.calculation->>'mode', 'normal') = 'normal'";
            $created = $base.' AND c.created_at >= :from AND c.created_at < :to';
            $paid = $base." AND c.status = 'paid' AND p.paid_at >= :from AND p.paid_at < :to";
            $summarySql = 'SELECT COUNT(*) AS count, COALESCE(SUM(c.gross_amount), 0) AS gross, COALESCE(SUM(c.deductions), 0) AS deductions, COALESCE(SUM(c.net_amount), 0) AS net, COALESCE(SUM(c.net_amount) FILTER (WHERE c.status IN (\'pending\', \'approved\')), 0) AS pending FROM commission c WHERE '.$created;
            $summary = $this->connection->fetchAssociative($summarySql, $params);
            $paidTotalsSql = 'SELECT COUNT(*) AS count, COALESCE(SUM(p.amount), 0) AS amount FROM commission c JOIN payment_link p ON p.commission_id = c.id WHERE '.$paid;
            $paidTotals = $this->connection->fetchAssociative($paidTotalsSql, $params);
            $returnsBase = "FROM adjustment a JOIN commission c ON c.id = a.commission_id WHERE a.customer_code = :customer AND a.type = 'return' AND ".$created;
            $returnTotalsSql = 'SELECT COUNT(*) AS count, COALESCE(SUM(a.amount), 0) AS amount '.$returnsBase;
            $returnTotals = $this->connection->fetchAssociative($returnTotalsSql, $params);

            $size = true === $export ? 1000 : 5;
            $paidPage = true === $export ? 1 : $input->paidPage;
            $returnPage = true === $export ? 1 : $input->returnsPage;
            $paidSql = 'SELECT c.id, c.code, c.gross_amount, c.deductions, c.net_amount, p.amount, p.manual_amount, TO_CHAR(p.paid_at, \'YYYY-MM-DD\') AS paid_at FROM commission c JOIN payment_link p ON p.commission_id = c.id WHERE '.$paid.' ORDER BY p.paid_at DESC, c.id DESC LIMIT '.$size.' OFFSET '.(($paidPage - 1) * $size);
            $paidItems = array_map(static fn (array $row): PaidCommissionOutput => new PaidCommissionOutput((int) $row['id'], $row['code'], $row['paid_at'], $row['gross_amount'], $row['deductions'], $row['net_amount'], $row['amount'], true === $row['manual_amount']), $this->connection->fetchAllAssociative($paidSql, $params));
            $returnsSql = 'SELECT a.id, a.source_reference, a.amount, a.reason, c.id AS commission_id, c.code, c.status, TO_CHAR(c.created_at, \'YYYY-MM-DD\') AS applied_at '.$returnsBase.' ORDER BY c.created_at DESC, a.id DESC LIMIT '.$size.' OFFSET '.(($returnPage - 1) * $size);
            $returnItems = array_map(static fn (array $row): AppliedReturnOutput => new AppliedReturnOutput((int) $row['id'], $row['source_reference'], $row['amount'], $row['applied_at'], (int) $row['commission_id'], $row['code'], $row['status'], $row['reason']), $this->connection->fetchAllAssociative($returnsSql, $params));

            $monthlySql = 'SELECT month, SUM(generated) AS generated, SUM(paid) AS paid FROM (SELECT TO_CHAR(c.created_at, \'YYYY-MM\') AS month, SUM(c.net_amount) AS generated, 0 AS paid FROM commission c WHERE '.$created.' GROUP BY month UNION ALL SELECT TO_CHAR(p.paid_at, \'YYYY-MM\') AS month, 0 AS generated, SUM(p.amount) AS paid FROM commission c JOIN payment_link p ON p.commission_id = c.id WHERE '.$paid.' GROUP BY month) flow GROUP BY month ORDER BY month';
            $monthly = array_map(static fn (array $row): CommissionMonthOutput => new CommissionMonthOutput($row['month'], $row['generated'], $row['paid']), $this->connection->fetchAllAssociative($monthlySql, $params));

            return new ResellerFinanceOutput(
                new FinanceSummaryOutput((int) $summary['count'], $summary['gross'], $summary['deductions'], $summary['net'], $summary['pending'], (int) $paidTotals['count'], $paidTotals['amount'], $returnTotals['amount']),
                new PaidCommissionsOutput($paidItems, (int) $paidTotals['count'], $paidPage, $size),
                new AppliedReturnsOutput($returnItems, (int) $returnTotals['count'], $returnPage, $size),
                $monthly,
            );
        });
    }
}
