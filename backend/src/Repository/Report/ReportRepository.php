<?php

declare(strict_types=1);

namespace App\Repository\Report;

use Doctrine\DBAL\Connection;
use Traversable;

final class ReportRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function summary(string $from, string $to): array
    {
        $params = ['from' => $from, 'to' => $to];
        $totalsSql = <<<'SQL'
            SELECT COUNT(*) AS count, COALESCE(SUM(gross_amount), 0) AS gross,
                   COALESCE(SUM(deductions), 0) AS deductions,
                   COALESCE(SUM((SELECT p.amount FROM payment_link p WHERE p.commission_id = commission.id)) FILTER (WHERE status = 'paid'), 0) AS paid,
                   COALESCE(SUM(net_amount) FILTER (WHERE status <> 'paid'), 0) AS outstanding
            FROM commission WHERE status <> 'rejected' AND created_at >= :from AND created_at < :to
            SQL;
        $statusSql = <<<'SQL'
            SELECT status, COUNT(*) AS count,
                   SUM(CASE WHEN status = 'paid' THEN
                       (SELECT p.amount FROM payment_link p WHERE p.commission_id = commission.id)
                       ELSE net_amount END) AS amount
            FROM commission WHERE status <> 'rejected' AND created_at >= :from AND created_at < :to
            GROUP BY status
            SQL;
        $customerSql = <<<'SQL'
            SELECT customer_code, customer_name, COUNT(*) AS count, SUM(net_amount) AS amount
            FROM commission WHERE status <> 'rejected' AND created_at >= :from AND created_at < :to
            GROUP BY customer_code, customer_name ORDER BY amount DESC LIMIT 20
            SQL;
        $monthlySql = <<<'SQL'
            SELECT TO_CHAR(created_at, 'YYYY-MM') AS month, SUM(net_amount) AS amount
            FROM commission WHERE status <> 'rejected' AND created_at >= :from AND created_at < :to
            GROUP BY month ORDER BY month
            SQL;

        return ['totals' => $this->connection->fetchAssociative($totalsSql, $params),
            'byStatus' => $this->connection->fetchAllAssociative($statusSql, $params),
            'byCustomer' => $this->connection->fetchAllAssociative($customerSql, $params),
            'monthly' => $this->connection->fetchAllAssociative($monthlySql, $params)];
    }

    public function export(string $from, string $to, array $filters = [], string $kind = 'commissions'): Traversable
    {
        [$sql, $parameters] = $this->definition($from, $to, $filters, $kind);

        return $this->connection->iterateAssociative($sql.' ORDER BY created_at DESC, id DESC', $parameters);
    }

    public function search(string $from, string $to, array $filters, string $kind, int $page): array
    {
        [$sql, $parameters] = $this->definition($from, $to, $filters, $kind);
        $amount = 'commissions' === $kind ? "CASE WHEN status = 'rejected' THEN 0 ELSE net_amount END" : 'amount';
        $totalsSql = 'SELECT COUNT(*) AS count, COALESCE(SUM('.$amount.'), 0) AS amount FROM ('.$sql.') report';
        $totals = $this->connection->fetchAssociative($totalsSql, $parameters);
        $rowsSql = $sql.' ORDER BY created_at DESC, id DESC LIMIT 30 OFFSET '.(($page - 1) * 30);

        return ['items' => $this->connection->fetchAllAssociative($rowsSql, $parameters), 'total' => (int) $totals['count'], 'amount' => (string) $totals['amount'], 'page' => $page];
    }

    private function definition(string $from, string $to, array $filters, string $kind): array
    {
        $parameters = ['from' => $from, 'to' => $to];
        $where = [];

        if ('commissions' === $kind) {
            $dateColumn = 'paid' === ($filters['dateBasis'] ?? 'created') ? 'p.paid_at' : 'c.created_at';
            $sql = <<<'SQL'
                SELECT c.code, c.customer_code, c.customer_name, c.gross_amount,
                       c.deductions, c.net_amount, c.status, c.created_at,
                       c.rejection_reason, c.rejected_at, (SELECT u.name FROM app_user u WHERE u.id = c.rejected_by_id) AS rejected_by,
                       p.routine, p.reference, p.verification, p.paid_at,
                       p.amount AS paid_amount, p.calculated_amount, p.manual_reason,
                       CASE WHEN p.manual_amount = TRUE THEN 'Sim' WHEN p.manual_amount = FALSE THEN 'Não' ELSE NULL END AS manual_amount,
                       COALESCE(c.calculation->>'mode', 'normal') AS mode,
                       (SELECT STRING_AGG(o.order_number || ' · NF ' || COALESCE(o.header->>'NUMNOTA', o.header->>'NUMCUPOM', '—'), ', ' ORDER BY o.order_number)
                        FROM order_snapshot o WHERE o.commission_id = c.id OR EXISTS (SELECT 1 FROM commission_rejected_order a WHERE a.order_snapshot_id = o.id AND a.commission_id = c.id)) AS orders,
                       c.id
                FROM commission c
                LEFT JOIN payment_link p ON p.commission_id = c.id
                SQL;

            if (true === isset($filters['orderNumber'])) {
                $where[] = 'EXISTS (SELECT 1 FROM order_snapshot o WHERE (o.commission_id = c.id OR EXISTS (SELECT 1 FROM commission_rejected_order a WHERE a.order_snapshot_id = o.id AND a.commission_id = c.id)) AND o.order_number = :order_number)';
                $parameters['order_number'] = $filters['orderNumber'];
            }

            if (true === isset($filters['status'])) {
                $where[] = 'c.status = :status';
                $parameters['status'] = $filters['status'];
            }

            if (true === isset($filters['mode'])) {
                $where[] = "COALESCE(c.calculation->>'mode', 'normal') = :mode";
                $parameters['mode'] = $filters['mode'];
            }

            $customerColumn = 'c.customer_code';
        } else {
            $dateColumn = 'applied' === ($filters['dateBasis'] ?? 'created') ? 'c.created_at' : 'a.created_at';
            $sql = <<<'SQL'
                SELECT a.customer_code, a.type, a.source_reference, a.reason, a.amount,
                       CASE WHEN a.commission_id IS NULL THEN 'pending' ELSE 'deducted' END AS state,
                       a.created_at, c.code AS commission_code, c.created_at AS deducted_at,
                       c.status AS commission_status, p.paid_at,
                       CASE WHEN a.source_snapshot->>'atg' = 'true' THEN 'atg'
                            WHEN a.source_snapshot->>'atg' = 'false' THEN 'normal'
                            ELSE 'unspecified' END AS mode, a.id, a.commission_id
                FROM adjustment a
                LEFT JOIN commission c ON c.id = a.commission_id
                LEFT JOIN payment_link p ON p.commission_id = c.id
                SQL;

            if (true === isset($filters['type'])) {
                $where[] = 'a.type = :type';
                $parameters['type'] = $filters['type'];
            }

            if (true === isset($filters['state'])) {
                $where[] = 'pending' === $filters['state'] ? 'a.commission_id IS NULL' : 'a.commission_id IS NOT NULL';
            }

            if (true === isset($filters['mode'])) {
                $where[] = "a.source_snapshot->>'atg' = :atg";
                $parameters['atg'] = 'atg' === $filters['mode'] ? 'true' : 'false';
            }

            if (true === isset($filters['status'])) {
                $where[] = 'c.status = :status';
                $parameters['status'] = $filters['status'];
            }

            $customerColumn = 'a.customer_code';
        }

        if (true === isset($filters['customer'])) {
            $where[] = $customerColumn.' = :customer';
            $parameters['customer'] = $filters['customer'];
        }

        $where[] = $dateColumn.' >= :from AND '.$dateColumn.' < :to';

        return [$sql.' WHERE '.implode(' AND ', $where), $parameters];
    }
}
