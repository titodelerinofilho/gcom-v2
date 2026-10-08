<?php

declare(strict_types=1);

namespace App\Repository\Winthor;

use App\Database\Statement\Statement;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\MovementGatewayInterface;

final readonly class MovementRepository implements MovementGatewayInterface
{
    public function __construct(private Statement $statement)
    {
    }

    public function returns(string $customer, bool $atg, ?string $transaction = null): array
    {
        // Keep normal and ATG eligibility separate, as in includes/functions.php.
        $eligibility = true === $atg
            ? '(M.NUMPED = 0 OR P.CONDVENDA IS NULL OR P.CONDVENDA <> 8)'
            : 'P.NUMPED IS NOT NULL AND P.CONDVENDA <> 8 AND C.CODPRACA NOT IN (573, 570, 539, 1097, 1098)';
        $period = null === $transaction
            ? 'M.DTMOV >= SYSDATE - 90 AND M.DTMOV <= SYSDATE'
            : 'M.NUMTRANSENT = :transaction';
        $customerFilter = true === $atg
            ? '(C.CODREVENDA = :customer OR C.CODCLI = :final_customer)'
            : 'C.CODREVENDA = :customer';
        $sql = <<<SQL
            SELECT M.*, C.CODCLI AS FINAL_CUSTOMER, C.CLIENTE,
                   NVL(C.CODREVENDA, C.CODCLI) AS PRINCIPAL,
                   C.CODPRACA AS PRACA, P.CONDVENDA, R.DESCRICAO,
                   TO_CHAR(M.DTMOV, 'YYYY-MM-DD') AS MOVEMENT_DATE
            FROM PCMOV M
            JOIN PCCLIENT C ON C.CODCLI = M.CODCLI
            LEFT JOIN PCPEDC P ON P.NUMPED = M.NUMPED
            JOIN PCPRODUT R ON R.CODPROD = M.CODPROD
            WHERE M.CODOPER = 'ED' AND M.DTCANCEL IS NULL AND M.CODDEVOL NOT IN (34)
              AND {$customerFilter}
              AND {$eligibility}
              AND {$period}
            ORDER BY M.DTMOV DESC, M.NUMTRANSENT, M.CODPROD
            SQL;
        $parameters = ['customer' => $customer];

        if (null !== $transaction) {
            $parameters['transaction'] = $transaction;

            if (true === $atg) {
                $parameters['final_customer'] = $customer;
            }
        }

        return $this->statement->transaction(function () use ($sql, $parameters, $transaction): array {
            $rows = $this->statement->query($sql, $parameters)->fetchAllAssociative();

            if (null !== $transaction && [] === $rows) {
                throw new BusinessException('Devolução inexistente ou fora dos critérios do legado.', 404);
            }

            $pricesSql = <<<'SQL'
                SELECT NUMREGIAO, PTABELA1
                FROM PCTABPR WHERE CODPROD = :product
                SQL;
            $prices = [];
            foreach ($rows as &$row) {
                $product = (string) $row['CODPROD'];

                if (false === isset($prices[$product])) {
                    $prices[$product] = [];
                    foreach ($this->statement->query($pricesSql, ['product' => $product])->fetchAllAssociative() as $price) {
                        $region = (string) $price['NUMREGIAO'];

                        if (true === array_key_exists($region, $prices[$product])) {
                            throw new BusinessException('Preço de devolução duplicado para produto/região.');
                        }

                        $prices[$product][$region] = $price['PTABELA1'];
                    }
                }

                $row['COMMISSION_RETURN_PRICES'] = $prices[$product];
            }
            unset($row);

            return $rows;
        });
    }

    public function cancellations(string $customer, string $from, string $to): array
    {
        $sql = <<<'SQL'
            SELECT M.NUMPED, M.NUMNOTA, M.CODPROD, R.DESCRICAO, M.CODCLI,
                   C.CLIENTE, C.CODREVENDA AS PRINCIPAL, M.CODFILIAL,
                   M.NUMTRANSVENDA, M.QT, M.PUNIT, M.QT * M.PUNIT AS VLTOTAL,
                   M.FUNCLANC, TO_CHAR(M.DTCANCEL, 'YYYY-MM-DD HH24:MI:SS') AS CANCELLED_AT,
                   (SELECT N.MOTIVO FROM PCNFCAN N WHERE N.NUMTRANSVENDA = M.NUMTRANSVENDA) AS MOTIVO
            FROM PCMOV M
            JOIN PCCLIENT C ON C.CODCLI = M.CODCLI
            JOIN PCPRODUT R ON R.CODPROD = M.CODPROD
            WHERE M.DTCANCEL IS NOT NULL AND M.CODOPER = 'S' AND M.QT < 0
              AND C.CODREVENDA = :customer
              AND M.DTMOV >= TO_DATE(:date_from, 'YYYY-MM-DD')
              AND M.DTMOV < TO_DATE(:date_to, 'YYYY-MM-DD') + 1
            ORDER BY M.DTCANCEL DESC, M.NUMPED, M.CODPROD
            SQL;

        return $this->statement->query($sql, ['customer' => $customer, 'date_from' => $from, 'date_to' => $to])->fetchAllAssociative();
    }

    public function cancelledOrder(string $number): array
    {
        $sql = <<<'SQL'
            SELECT M.NUMPED, M.CODPROD, M.QT, M.PUNIT, M.NUMTRANSVENDA,
                   M.NUMNOTA, M.CODFILIAL, M.FUNCLANC,
                   TO_CHAR(M.DTCANCEL, 'YYYY-MM-DD HH24:MI:SS') AS CANCELLED_AT
            FROM PCMOV M
            WHERE M.NUMPED = :order_number
              AND M.DTCANCEL IS NOT NULL AND M.CODOPER = 'S' AND M.QT < 0
            ORDER BY M.NUMTRANSVENDA, M.CODPROD
            SQL;

        return $this->statement->query($sql, ['order_number' => $number])->fetchAllAssociative();
    }

    public function overdue(string $customer): array
    {
        $sql = <<<'SQL'
            SELECT * FROM (
                SELECT P.NUMTRANSVENDA, P.PREST, P.NUMNOTA, P.CODCLI, C.CLIENTE,
                       P.CODFILIAL, P.CODCOB, B.BOLETO, P.VALOR,
                       TO_CHAR(P.DTVENC, 'YYYY-MM-DD') AS DUE_DATE,
                       NVL(F_QTDIASVENCIDOS(TRUNC(CASE
                           WHEN P.DTRECEBIMENTOPREVISTO IS NULL THEN P.DTVENC
                           WHEN TRUNC(P.DTRECEBIMENTOPREVISTO) > TRUNC(SYSDATE)
                             OR TRUNC(P.DTRECEBIMENTOPREVISTO) > TRUNC(P.DTVENC)
                           THEN P.DTRECEBIMENTOPREVISTO ELSE P.DTVENC END),
                           NVL(P.DTPAG, TRUNC(SYSDATE)), P.CODCOB, P.CODFILIAL,
                           NVL(F.USADIAUTILFILIAL, 'N'), 'S'), 0) AS ATRASO
                FROM PCPREST P
                JOIN PCCLIENT C ON C.CODCLI = P.CODCLI
                LEFT JOIN PCCOB B ON B.CODCOB = P.CODCOB
                LEFT JOIN PCFILIAL F ON F.CODIGO = P.CODFILIAL
                WHERE C.CODREVENDA = :customer
                  AND P.DTCANCEL IS NULL AND P.CODCOB <> 'CANC' AND P.DTPAG IS NULL
                  AND TRUNC(P.DTEMISSAO) >= DATE '2000-01-01'
                  AND TRUNC(P.DTEMISSAO) <= TRUNC(SYSDATE)
            ) T
            WHERE T.ATRASO > 0 AND (T.BOLETO = 'S' OR T.CODCOB IN ('ECOB', 'SMC'))
            ORDER BY T.DUE_DATE, T.NUMTRANSVENDA, T.PREST
            SQL;

        return $this->statement->query($sql, ['customer' => $customer])->fetchAllAssociative();
    }
}
