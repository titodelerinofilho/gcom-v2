<?php

declare(strict_types=1);

namespace App\Repository\Winthor;

use App\Database\Statement\Statement;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\OrderGatewayInterface;

final readonly class OrderRepository implements OrderGatewayInterface
{
    public function __construct(
        private Statement $statement,
    ) {
    }

    public function assertEligible(array $numbers): void
    {
        $sql = <<<'SQL'
            SELECT P.NUMPED
            FROM PCPEDC P
            WHERE P.NUMPED = :order_number
              AND P.POSICAO = 'F'
              AND P.DTCANCEL IS NULL
              AND P.CONDVENDA IN (1, 7)
              AND P.CODCOB <> 'ORDS'
              AND NOT EXISTS (
                  SELECT 1 FROM PCMOV M
                  WHERE M.NUMPED = P.NUMPED
                    AND M.CODOPER = 'S' AND M.DTCANCEL IS NOT NULL AND M.QT < 0
              )
            SQL;

        foreach ($numbers as $number) {
            $stmt = $this->statement->query($sql, ['order_number' => $number])->fetchOne();

            if (false === $stmt) {
                throw new BusinessException('Pedido '.$number.' cancelado ou fora dos critérios de comissão do legado.', 409);
            }
        }
    }

    public function assertCommissionEligible(array $numbers, string $customer, string $mode, int $square): void
    {
        $this->assertEligible($numbers);
        $customerFilter = 'normal' === $mode ? 'C.CODREVENDA = :customer' : 'NVL(C.CODREVENDA, C.CODCLI) = :customer';
        $squareEligibility = 'normal' === $mode ? 'AND P.CODPRACA NOT IN (573, 539, 570, 1097, 1098, 1103)' : '';
        $sql = <<<SQL
            SELECT P.NUMPED FROM PCPEDC P
            JOIN PCCLIENT C ON C.CODCLI = P.CODCLI
            WHERE P.NUMPED = :order_number AND P.CODPRACA = :square
              AND {$customerFilter}
              {$squareEligibility}
            SQL;

        foreach ($numbers as $number) {
            if (false === $this->statement->query($sql, ['order_number' => $number, 'square' => $square, 'customer' => $customer])->fetchOne()) {
                throw new BusinessException('Pedido '.$number.' fora da praça ou do vínculo de cliente selecionado. Refaça a busca.', 409);
            }
        }
    }

    public function fetch(string $orderNumber): array
    {
        if (1 !== preg_match('/^[1-9][0-9]{0,11}$/D', $orderNumber)) {
            throw new BusinessException('Número do pedido inválido.');
        }

        return $this->statement->transaction(function () use ($orderNumber): array {
            $this->assertEligible([$orderNumber]);

            $headerSql = <<<'SQL'
                SELECT P.*, TO_CHAR(P.DATA, 'YYYY-MM-DD HH24:MI:SS') AS DATA_ISO
                FROM PCPEDC P
                WHERE P.NUMPED = :order_number
                SQL;

            $header = $this->statement->query($headerSql, ['order_number' => $orderNumber])->fetchAssociative();

            if (false === $header) {
                throw new BusinessException('Pedido não encontrado.', 404);
            }

            $customerSql = <<<'SQL'
                SELECT C.CODCLI, C.CLIENTE, NVL(C.CODREVENDA, C.CODCLI) AS PRINCIPAL,
                       NVL(A.CLIENTE, C.CLIENTE) AS PRINCIPAL_NAME
                FROM PCCLIENT C
                LEFT JOIN PCCLIENT A ON A.CODCLI = C.CODREVENDA
                WHERE C.CODCLI = :customer
                SQL;

            $customer = $this->statement
                ->query($customerSql, ['customer' => $header['CODCLI']])
                ->fetchAssociative();

            if (false === $customer) {
                throw new BusinessException('Cliente do pedido não encontrado.');
            }

            $header['COMMISSION_PRINCIPAL'] = (string) $customer['PRINCIPAL'];
            $header['COMMISSION_FINAL_CUSTOMER_NAME'] = $customer['CLIENTE'];
            $header['COMMISSION_INVOICES'] = $this->invoices($orderNumber);

            $itemsSql = <<<'SQL'
                SELECT I.*, R.DESCRICAO
                FROM PCPEDI I
                JOIN PCPRODUT R ON R.CODPROD = I.CODPROD
                WHERE I.NUMPED = :order_number AND I.POSICAO = 'F'
                ORDER BY I.CODPROD, I.NUMSEQ
                SQL;

            $items = $this->statement
                ->query($itemsSql, ['order_number' => $orderNumber])
                ->fetchAllAssociative();

            if ([] === $items) {
                throw new BusinessException('Pedido sem itens faturados.');
            }

            $planSql = <<<'SQL'
                SELECT NUMPR FROM PCPLPAG WHERE CODPLPAG = :plan
                SQL;

            $header['COMMISSION_NUMPR'] = $this->statement->query($planSql, ['plan' => $header['CODPLPAG']])->fetchOne();

            $pricesSql = <<<'SQL'
                SELECT T.CODPROD, T.NUMREGIAO, T.PTABELA1,
                       T.PVENDA1, T.PVENDA2, T.PVENDA3, T.PVENDA4,
                       T.PVENDA5, T.PVENDA6, T.PVENDA7
                FROM PCTABPR T
                WHERE EXISTS (
                    SELECT 1 FROM PCPEDI I
                    WHERE I.NUMPED = :order_number AND I.CODPROD = T.CODPROD
                )
                SQL;

            $rows = $this->statement
                ->query($pricesSql, ['order_number' => $orderNumber])
                ->fetchAllAssociative();

            $prices = [];

            foreach ($rows as $price) {
                $product = (string) $price['CODPROD'];
                $region = (string) $price['NUMREGIAO'];

                if (true === isset($prices[$product][$region])) {
                    throw new BusinessException('Preço duplicado em PCTABPR para produto/região. Valide a chave da tabela com o DBA.');
                }

                $prices[$product][$region] = $price;
            }

            $compositionSql = <<<'SQL'
                SELECT P.*, PC.CODPRECOCESTA, PC.NUMREGIAO AS COMPOSITION_REGION,
                       PC.CODFILIAL AS COMPOSITION_BRANCH, T.PVENDA1 AS PSD_UNIT
                FROM PCPEDICESTA P
                JOIN PCPEDC H ON H.NUMPED = P.NUMPED
                JOIN PCPRECOCESTAC PC ON PC.CODPRODACAB = P.CODPROD
                    AND PC.CODFILIAL = H.CODFILIAL AND PC.DTEXCLUSAO IS NULL
                LEFT JOIN PCTABPR T ON T.CODPROD = P.CODPRODMP AND T.NUMREGIAO = PC.NUMREGIAO
                WHERE P.NUMPED = :order_number
                  AND EXISTS (SELECT 1 FROM PCPRECOCESTAI PI WHERE PI.CODPRECOCESTA = PC.CODPRECOCESTA)
                ORDER BY P.CODPROD, PC.CODPRECOCESTA, P.CODPRODMP
                SQL;

            $compositions = [];

            // Ordinary orders do not depend on the optional combo tables.
            if (true === array_any($items, static fn (array $i): bool => str_contains(mb_strtoupper($i['DESCRICAO']), 'COMBO'))) {
                $componentPricesSql = <<<'SQL'
                    SELECT T.CODPROD, T.NUMREGIAO, T.PVENDA1
                    FROM PCTABPR T
                    WHERE EXISTS (
                        SELECT 1 FROM PCPEDICESTA P
                        WHERE P.NUMPED = :order_number AND P.CODPRODMP = T.CODPROD
                    )
                    SQL;

                $componentPrices = [];

                $componentPricesItems = $this->statement
                    ->query($componentPricesSql, ['order_number' => $orderNumber])
                    ->fetchAllAssociative();

                foreach ($componentPricesItems as $price) {
                    $product = (string) $price['CODPROD'];
                    $region = (string) $price['NUMREGIAO'];

                    if (true === isset($componentPrices[$product][$region])) {
                        throw new BusinessException('Preço de componente duplicado em PCTABPR.');
                    }

                    $componentPrices[$product][$region] = $price['PVENDA1'];
                }

                $components = $this->statement->query($compositionSql, ['order_number' => $orderNumber])->fetchAllAssociative();

                foreach ($components as $component) {
                    $component['COMMISSION_COMPONENT_PRICES'] = $componentPrices[(string) $component['CODPRODMP']] ?? [];
                    $compositions[(string) $component['CODPROD']][] = $component;
                }
            }

            foreach ($items as &$item) {
                $item['COMMISSION_COMPOSITION'] = $compositions[(string) $item['CODPROD']] ?? [];
                $item['COMMISSION_PRICES'] = $prices[(string) $item['CODPROD']] ?? [];
            }

            unset($item);

            return [
                'header' => $header,
                'customerName' => $customer['PRINCIPAL_NAME'],
                'items' => $items,
            ];
        });
    }

    public function inspect(string $orderNumber): array
    {
        return $this->statement->transaction(function () use ($orderNumber): array {
            $headerSql = 'SELECT P.* FROM PCPEDC P WHERE P.NUMPED = :order_number';
            $header = $this->statement->query($headerSql, ['order_number' => $orderNumber])->fetchAssociative();
            $itemsSql = <<<'SQL'
                SELECT I.*, R.DESCRICAO FROM PCPEDI I
                LEFT JOIN PCPRODUT R ON R.CODPROD = I.CODPROD
                WHERE I.NUMPED = :order_number
                ORDER BY I.CODPROD, I.NUMSEQ
                SQL;

            return ['header' => false === $header ? null : $header,
                'items' => $this->statement->query($itemsSql, ['order_number' => $orderNumber])->fetchAllAssociative(),
                'invoices' => $this->invoices($orderNumber)];
        });
    }

    private function invoices(string $orderNumber): array
    {
        $sql = <<<'SQL'
            SELECT S.* FROM PCNFSAID S
            WHERE S.NUMPED = :order_number
               OR S.NUMTRANSVENDA IN (SELECT P.NUMTRANSVENDA FROM PCPEDC P WHERE P.NUMPED = :sale_order)
            ORDER BY S.NUMTRANSVENDA
            SQL;

        return $this->statement->query($sql, ['order_number' => $orderNumber, 'sale_order' => $orderNumber])->fetchAllAssociative();
    }

    public function search(string $customer, string $from, string $to, ?int $square, ?string $mode = null): array
    {
        $customerFilter = 'normal' === $mode ? 'C.CODREVENDA = :customer' : 'NVL(C.CODREVENDA, C.CODCLI) = :customer';
        $squareEligibility = 'normal' === $mode ? 'AND P.CODPRACA NOT IN (573, 539, 570, 1097, 1098, 1103)' : '';
        $sql = <<<SQL
            SELECT P.NUMPED, P.CODFILIAL, P.NUMREGIAO, P.CODPRACA, P.CODPLPAG,
                   L.NUMPR AS COMMISSION_NUMPR,
                   P.VLTOTAL, P.VLFRETE, P.CODCLI, C.CLIENTE,
                   NVL(P.NUMNOTA, P.NUMCUPOM) AS NUMNOTA,
                   TO_CHAR(P.DATA, 'YYYY-MM-DD') AS DATA
            FROM PCPEDC P
            JOIN PCCLIENT C ON C.CODCLI = P.CODCLI
            LEFT JOIN PCPLPAG L ON L.CODPLPAG = P.CODPLPAG
            WHERE {$customerFilter}
              {$squareEligibility}
              AND P.CONDVENDA IN (1, 7) AND P.CODCOB <> 'ORDS'
              AND P.POSICAO = 'F' AND P.DTCANCEL IS NULL
              AND P.DATA >= TO_DATE(:date_from, 'YYYY-MM-DD')
              AND P.DATA < TO_DATE(:date_to, 'YYYY-MM-DD') + 1
              AND (:square IS NULL OR P.CODPRACA = :square_value)
              AND EXISTS (SELECT 1 FROM PCPEDI I WHERE I.NUMPED = P.NUMPED AND I.POSICAO = 'F')
            ORDER BY P.DATA DESC, P.NUMPED DESC
            FETCH FIRST 500 ROWS ONLY
            SQL;

        return $this->statement
            ->query($sql, [
                'customer' => $customer,
                'date_from' => $from,
                'date_to' => $to,
                'square' => $square,
                'square_value' => $square,
            ])->fetchAllAssociative();
    }
}
