<?php

declare(strict_types=1);

namespace App\Repository\Winthor;

use App\Database\Statement\Statement;
use App\Dto\Reseller\Input\GetResellerProfileInput;
use App\Dto\Reseller\Output\ActivityOutput;
use App\Dto\Reseller\Output\CancellationOutput;
use App\Dto\Reseller\Output\CancellationsOutput;
use App\Dto\Reseller\Output\CustomerOutput;
use App\Dto\Reseller\Output\DebtOutput;
use App\Dto\Reseller\Output\DebtsOutput;
use App\Dto\Reseller\Output\DebtSummaryOutput;
use App\Dto\Reseller\Output\ResellerOracleOutput;
use App\Dto\Reseller\Output\SalesMonthOutput;
use App\Dto\Reseller\Output\TopCustomerOutput;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\ResellerGatewayInterface;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class ResellerRepository implements ResellerGatewayInterface
{
    public function __construct(private Statement $statement)
    {
    }

    public function get(GetResellerProfileInput $input, bool $export = false): ResellerOracleOutput
    {
        return $this->statement->transaction(function () use ($input, $export): ResellerOracleOutput {
            $customerSql = 'SELECT CODCLI, CLIENTE, CODREVENDA FROM PCCLIENT WHERE CODCLI = :customer';
            $customer = $this->statement->query($customerSql, ['customer' => $input->customer])->fetchAssociative();

            if (false === $customer) {
                throw new BusinessException('Cliente não encontrado no Winthor.', 404);
            }

            if (null !== $customer['CODREVENDA'] && '0' !== (string) $customer['CODREVENDA'] && $input->customer !== (string) $customer['CODREVENDA']) {
                throw new BusinessException('Este cliente está vinculado ao revenda '.$customer['CODREVENDA'].'. Consulte o código do cliente principal.', 409);
            }

            $parameters = ['customer' => $input->customer, 'date_from' => $input->from, 'date_to' => $input->to];
            $soldDefinition = <<<'SQL'
                FROM PCPEDC P JOIN PCCLIENT C ON C.CODCLI = P.CODCLI
                WHERE C.CODREVENDA = :customer AND C.CODCLI <> :customer
                  AND P.CONDVENDA IN (1, 7) AND P.CODCOB <> 'ORDS'
                  AND P.CODPRACA NOT IN (573, 539, 570, 1097, 1098, 1103)
                  AND P.POSICAO = 'F' AND P.DTCANCEL IS NULL
                  AND P.DATA >= TO_DATE(:date_from, 'YYYY-MM-DD')
                  AND P.DATA < TO_DATE(:date_to, 'YYYY-MM-DD') + 1
                  AND EXISTS (SELECT 1 FROM PCPEDI I WHERE I.NUMPED = P.NUMPED AND I.POSICAO = 'F')
                SQL;
            $soldSql = 'SELECT COUNT(*) AS ORDERS, NVL(SUM(P.VLTOTAL), 0) AS AMOUNT, COUNT(DISTINCT P.CODCLI) AS ACTIVE '.$soldDefinition;
            $sold = $this->statement->query($soldSql, $parameters)->fetchAssociative();
            $linkedSql = 'SELECT COUNT(*) AS TOTAL FROM PCCLIENT WHERE CODREVENDA = :customer AND CODCLI <> :customer';
            $linked = $this->statement->query($linkedSql, ['customer' => $input->customer])->fetchAssociative();
            $monthlySql = "SELECT TO_CHAR(P.DATA, 'YYYY-MM') AS MONTH, COUNT(*) AS ORDERS, SUM(P.VLTOTAL) AS AMOUNT ".$soldDefinition." GROUP BY TO_CHAR(P.DATA, 'YYYY-MM') ORDER BY MONTH";
            $monthly = array_map(static fn (array $row): SalesMonthOutput => new SalesMonthOutput($row['MONTH'], (int) $row['ORDERS'], self::amount($row['AMOUNT'])), $this->statement->query($monthlySql, $parameters)->fetchAllAssociative());
            $topSql = 'SELECT C.CODCLI, C.CLIENTE, COUNT(*) AS ORDERS, SUM(P.VLTOTAL) AS AMOUNT '.$soldDefinition.' GROUP BY C.CODCLI, C.CLIENTE ORDER BY AMOUNT DESC, C.CODCLI FETCH FIRST 5 ROWS ONLY';
            $top = array_map(static fn (array $row): TopCustomerOutput => new TopCustomerOutput((string) $row['CODCLI'], $row['CLIENTE'], (int) $row['ORDERS'], self::amount($row['AMOUNT'])), $this->statement->query($topSql, $parameters)->fetchAllAssociative());
            $returnsSql = <<<'SQL'
                SELECT NVL(SUM(ABS(M.QT * M.PUNIT)), 0) AS AMOUNT
                FROM PCMOV M JOIN PCCLIENT C ON C.CODCLI = M.CODCLI
                JOIN PCPEDC P ON P.NUMPED = M.NUMPED
                WHERE C.CODREVENDA = :customer AND C.CODCLI <> :customer
                  AND M.CODOPER = 'ED' AND M.DTCANCEL IS NULL AND M.CODDEVOL NOT IN (34)
                  AND P.CONDVENDA IN (1, 7) AND P.CODCOB <> 'ORDS'
                  AND P.CODPRACA NOT IN (573, 539, 570, 1097, 1098, 1103)
                  AND M.DTMOV >= TO_DATE(:date_from, 'YYYY-MM-DD')
                  AND M.DTMOV < TO_DATE(:date_to, 'YYYY-MM-DD') + 1
                SQL;
            $returned = $this->statement->query($returnsSql, $parameters)->fetchAssociative();
            $cancellationDefinition = $this->cancellationDefinition();
            $cancelTotalsSql = 'SELECT COUNT(*) AS TOTAL, NVL(SUM(AMOUNT), 0) AS AMOUNT FROM ('.$cancellationDefinition.')';
            $cancelTotals = $this->statement->query($cancelTotalsSql, $parameters)->fetchAssociative();
            $cancelSize = true === $export ? 1000 : 5;
            $cancelPage = true === $export ? 1 : $input->cancellationsPage;
            $cancellationsSql = 'SELECT * FROM ('.$cancellationDefinition.') ORDER BY CANCELLED_AT DESC, NUMPED DESC OFFSET '.(($cancelPage - 1) * $cancelSize).' ROWS FETCH NEXT '.$cancelSize.' ROWS ONLY';
            $cancelItems = array_map(static fn (array $row): CancellationOutput => new CancellationOutput((string) $row['NUMPED'], (string) $row['CODCLI'], $row['CLIENTE'], $row['CANCELLED_AT'], self::amount($row['AMOUNT']), $row['REASON'] ?? 'Não informado', $row['SOURCE']), $this->statement->query($cancellationsSql, $parameters)->fetchAllAssociative());
            [$debtSummary, $debts] = $this->debts($input, $export);

            return new ResellerOracleOutput(
                new CustomerOutput((string) $customer['CODCLI'], $customer['CLIENTE']),
                new ActivityOutput((int) $sold['ORDERS'], self::amount($sold['AMOUNT']), (int) $linked['TOTAL'], (int) $sold['ACTIVE'], (int) $cancelTotals['TOTAL'], self::amount($cancelTotals['AMOUNT']), self::amount($returned['AMOUNT'])),
                $debtSummary,
                $debts,
                new CancellationsOutput($cancelItems, (int) $cancelTotals['TOTAL'], $cancelPage, $cancelSize),
                $monthly,
                $top,
            );
        });
    }

    /** @return array{DebtSummaryOutput, DebtsOutput} */
    private function debts(GetResellerProfileInput $input, bool $export): array
    {
        $definition = <<<'SQL'
            SELECT P.NUMTRANSVENDA, P.PREST, P.CODCLI, C.CLIENTE, P.VALOR,
                   (SELECT MAX(S.NUMNOTA) FROM PCNFSAID S WHERE S.NUMTRANSVENDA = P.NUMTRANSVENDA) AS NUMNOTA,
                   TO_CHAR(P.DTVENC, 'YYYY-MM-DD') AS DUE_DATE,
                   CASE WHEN P.DTVENC IS NULL THEN 0 ELSE
                       NVL(F_QTDIASVENCIDOS(TRUNC(CASE
                           WHEN P.DTRECEBIMENTOPREVISTO IS NULL THEN P.DTVENC
                           WHEN TRUNC(P.DTRECEBIMENTOPREVISTO) > TRUNC(SYSDATE)
                             OR TRUNC(P.DTRECEBIMENTOPREVISTO) > TRUNC(P.DTVENC)
                           THEN P.DTRECEBIMENTOPREVISTO ELSE P.DTVENC END),
                           TRUNC(SYSDATE), P.CODCOB, P.CODFILIAL,
                           NVL(F.USADIAUTILFILIAL, 'N'), 'S'), 0) END AS LATE_DAYS
            FROM PCPREST P JOIN PCCLIENT C ON C.CODCLI = P.CODCLI
            LEFT JOIN PCFILIAL F ON F.CODIGO = P.CODFILIAL
            WHERE (C.CODREVENDA = :customer OR C.CODCLI = :principal)
              AND P.DTCANCEL IS NULL AND P.CODCOB <> 'CANC' AND P.DTPAG IS NULL
              AND TRUNC(P.DTEMISSAO) >= DATE '2000-01-01'
              AND TRUNC(P.DTEMISSAO) <= TRUNC(SYSDATE)
            SQL;
        $parameters = ['customer' => $input->customer, 'principal' => $input->customer];
        $summarySql = 'SELECT COUNT(*) AS TOTAL, NVL(SUM(VALOR), 0) AS OPEN_AMOUNT, NVL(SUM(CASE WHEN LATE_DAYS > 0 THEN 1 ELSE 0 END), 0) AS OVERDUE_COUNT, NVL(SUM(CASE WHEN LATE_DAYS > 0 THEN VALOR ELSE 0 END), 0) AS OVERDUE_AMOUNT, NVL(SUM(CASE WHEN LATE_DAYS > 0 AND CODCLI = :own_customer THEN VALOR ELSE 0 END), 0) AS OWN_AMOUNT, NVL(SUM(CASE WHEN LATE_DAYS > 0 AND CODCLI <> :linked_customer THEN VALOR ELSE 0 END), 0) AS LINKED_AMOUNT, NVL(MAX(LATE_DAYS), 0) AS MAX_LATE FROM ('.$definition.')';
        $summary = $this->statement->query($summarySql, [...$parameters, 'own_customer' => $input->customer, 'linked_customer' => $input->customer])->fetchAssociative();
        $size = true === $export ? 1000 : 10;
        $page = true === $export ? 1 : $input->debtsPage;
        $sql = 'SELECT * FROM ('.$definition.') ORDER BY CASE WHEN LATE_DAYS > 0 THEN 0 ELSE 1 END, DUE_DATE, CODCLI, NUMTRANSVENDA, PREST OFFSET '.(($page - 1) * $size).' ROWS FETCH NEXT '.$size.' ROWS ONLY';
        $items = array_map(static fn (array $row): DebtOutput => new DebtOutput((string) $row['CODCLI'], $row['CLIENTE'], $input->customer === (string) $row['CODCLI'], (string) $row['NUMTRANSVENDA'], (string) $row['PREST'], null === $row['NUMNOTA'] ? null : (string) $row['NUMNOTA'], $row['DUE_DATE'], max(0, (int) $row['LATE_DAYS']), self::amount($row['VALOR'])), $this->statement->query($sql, $parameters)->fetchAllAssociative());

        return [new DebtSummaryOutput((int) $summary['TOTAL'], self::amount($summary['OPEN_AMOUNT']), (int) $summary['OVERDUE_COUNT'], self::amount($summary['OVERDUE_AMOUNT']), self::amount($summary['OWN_AMOUNT']), self::amount($summary['LINKED_AMOUNT']), max(0, (int) $summary['MAX_LATE'])), new DebtsOutput($items, (int) $summary['TOTAL'], $page, $size)];
    }

    private function cancellationDefinition(): string
    {
        // Header cancellations and fiscal movements are grouped by order; items
        // cannot inflate counts and a header/fiscal cancellation is counted once.
        return <<<'SQL'
            SELECT P.NUMPED, C.CODCLI, C.CLIENTE,
                   TO_CHAR(P.DTCANCEL, 'YYYY-MM-DD') AS CANCELLED_AT,
                   ABS(NVL(P.VLTOTAL, 0)) AS AMOUNT, 'Cancelamento do pedido' AS REASON, 'order' AS SOURCE
            FROM PCPEDC P JOIN PCCLIENT C ON C.CODCLI = P.CODCLI
            WHERE C.CODREVENDA = :customer AND C.CODCLI <> :customer
              AND P.CONDVENDA IN (1, 7) AND P.CODCOB <> 'ORDS'
              AND P.CODPRACA NOT IN (573, 539, 570, 1097, 1098, 1103)
              AND P.DTCANCEL >= TO_DATE(:date_from, 'YYYY-MM-DD')
              AND P.DTCANCEL < TO_DATE(:date_to, 'YYYY-MM-DD') + 1
            UNION ALL
            SELECT M.NUMPED, C.CODCLI, C.CLIENTE,
                   TO_CHAR(MAX(M.DTCANCEL), 'YYYY-MM-DD') AS CANCELLED_AT,
                   SUM(ABS(M.QT * M.PUNIT)) AS AMOUNT,
                   MAX(N.MOTIVO) AS REASON,
                   'invoice' AS SOURCE
            FROM PCMOV M JOIN PCCLIENT C ON C.CODCLI = M.CODCLI
            JOIN PCPEDC P ON P.NUMPED = M.NUMPED
            LEFT JOIN (SELECT NUMTRANSVENDA, MAX(MOTIVO) AS MOTIVO FROM PCNFCAN GROUP BY NUMTRANSVENDA) N ON N.NUMTRANSVENDA = M.NUMTRANSVENDA
            WHERE C.CODREVENDA = :customer AND C.CODCLI <> :customer
              AND P.CONDVENDA IN (1, 7) AND P.CODCOB <> 'ORDS'
              AND P.CODPRACA NOT IN (573, 539, 570, 1097, 1098, 1103)
              AND M.CODOPER = 'S' AND M.QT < 0
              AND M.DTCANCEL >= TO_DATE(:date_from, 'YYYY-MM-DD')
              AND M.DTCANCEL < TO_DATE(:date_to, 'YYYY-MM-DD') + 1
              AND (P.DTCANCEL IS NULL OR NOT (P.DTCANCEL >= TO_DATE(:date_from, 'YYYY-MM-DD') AND P.DTCANCEL < TO_DATE(:date_to, 'YYYY-MM-DD') + 1))
            GROUP BY M.NUMPED, C.CODCLI, C.CLIENTE
            SQL;
    }

    private static function amount(mixed $value): string
    {
        return (string) BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HalfUp);
    }
}
