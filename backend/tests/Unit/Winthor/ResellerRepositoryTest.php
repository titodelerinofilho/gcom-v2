<?php

declare(strict_types=1);

namespace App\Tests\Unit\Winthor;

use App\Database\Connection\DatabaseConnection;
use App\Database\Statement\Statement;
use App\Dto\Reseller\Input\GetResellerProfileInput;
use App\Exception\Business\BusinessException;
use App\Repository\Winthor\ResellerRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ResellerRepositoryTest extends TestCase
{
    private function repository(): ResellerRepository
    {
        $dispatcher = new EventDispatcher();
        $connection = new DatabaseConnection('sqlite::memory:', '', '', $dispatcher);
        $db = new class('sqlite::memory:') extends PDO {
            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                // Adapt dialect/date/pagination only; execute the actual joins,
                // filters and aggregations over fixtures rather than mocking rows.
                $query = str_replace(['SET TRANSACTION READ ONLY', "DATE '2000-01-01'", 'SYSDATE', "TO_DATE(:date_to, 'YYYY-MM-DD') + 1"], ['SELECT 1', "'2000-01-01'", "'2026-10-09'", "date(:date_to, '+1 day')"], $query);
                $query = preg_replace('/OFFSET (\d+) ROWS FETCH NEXT (\d+) ROWS ONLY/', 'LIMIT $2 OFFSET $1', $query);
                $query = preg_replace('/FETCH FIRST (\d+) ROWS ONLY/', 'LIMIT $1', $query);

                return parent::prepare($query, $options);
            }
        };
        (new ReflectionProperty(DatabaseConnection::class, 'pdo'))->setValue($connection, $db);
        $db->sqliteCreateFunction('NVL', static fn ($value, $fallback) => $value ?? $fallback, 2);
        $db->sqliteCreateFunction('TRUNC', static fn (?string $value): ?string => $value, 1);
        $db->sqliteCreateFunction('TO_DATE', static fn (string $value, string $format): string => $value, 2);
        $db->sqliteCreateFunction('TO_CHAR', static fn (?string $value, string $format): ?string => null === $value ? null : substr($value, 0, 'YYYY-MM' === $format ? 7 : 10), 2);
        $db->sqliteCreateFunction('F_QTDIASVENCIDOS', static fn (string $due, string $today, string $collection, string $branch, string $business, string $mode): int => (int) ((strtotime($today) - strtotime($due)) / 86400), 6);
        $db->exec('CREATE TABLE PCCLIENT (CODCLI INT PRIMARY KEY, CLIENTE TEXT, CODREVENDA INT)');
        $db->exec('CREATE TABLE PCPEDC (NUMPED INT PRIMARY KEY, CODCLI INT, CONDVENDA INT, CODCOB TEXT, CODPRACA INT, POSICAO TEXT, DTCANCEL TEXT, DATA TEXT, VLTOTAL NUMERIC)');
        $db->exec('CREATE TABLE PCPEDI (NUMPED INT, POSICAO TEXT)');
        $db->exec('CREATE TABLE PCMOV (NUMPED INT, CODCLI INT, CODOPER TEXT, QT NUMERIC, PUNIT NUMERIC, DTCANCEL TEXT, DTMOV TEXT, CODDEVOL INT, NUMTRANSVENDA INT)');
        $db->exec('CREATE TABLE PCNFCAN (NUMTRANSVENDA INT, MOTIVO TEXT)');
        $db->exec('CREATE TABLE PCPREST (NUMTRANSVENDA INT, PREST TEXT, CODCLI INT, VALOR NUMERIC, DTVENC TEXT, DTRECEBIMENTOPREVISTO TEXT, CODCOB TEXT, CODFILIAL TEXT, DTCANCEL TEXT, DTPAG TEXT, DTEMISSAO TEXT)');
        $db->exec('CREATE TABLE PCFILIAL (CODIGO TEXT, USADIAUTILFILIAL TEXT)');
        $db->exec('CREATE TABLE PCNFSAID (NUMTRANSVENDA INT, NUMNOTA INT)');
        $db->exec("INSERT INTO PCCLIENT VALUES (100, 'Principal', NULL), (200, 'Vinculado', 100), (201, 'Sem vendas', 100), (300, 'Outro', 999)");
        $db->exec("INSERT INTO PCPEDC VALUES (1, 200, 1, 'BOL', 562, 'F', NULL, '2026-10-01', 1000), (2, 200, 1, 'BOL', 562, 'C', '2026-10-02', '2026-09-01', 300), (3, 200, 1, 'BOL', 562, 'F', NULL, '2026-10-03', 500), (4, 200, 1, 'BOL', 573, 'F', NULL, '2026-10-03', 900), (5, 100, 1, 'BOL', 562, 'F', NULL, '2026-10-03', 900), (6, 300, 1, 'BOL', 562, 'F', NULL, '2026-10-03', 900), (7, 200, 1, 'BOL', 562, 'F', NULL, '2025-10-03', 900)");
        $db->exec("INSERT INTO PCPEDI VALUES (1, 'F'), (1, 'F'), (3, 'F'), (4, 'F'), (5, 'F'), (6, 'F'), (7, 'F')");
        $db->exec("INSERT INTO PCMOV VALUES (2, 200, 'S', -2, 100, '2026-10-02', '2026-09-01', NULL, 20), (2, 200, 'S', -1, 100, '2026-10-02', '2026-09-01', NULL, 20), (3, 200, 'S', -1, 40, '2026-10-04', '2026-10-03', NULL, 30), (3, 200, 'S', -2, 30, '2026-10-04', '2026-10-03', NULL, 30), (1, 200, 'ED', 1, 20, NULL, '2026-10-05', 1, 10), (1, 200, 'ED', 1, 30, NULL, '2026-10-05', 1, 10), (1, 200, 'ED', 1, 900, NULL, '2026-10-05', 34, 10), (4, 200, 'ED', 1, 900, NULL, '2026-10-05', 1, 40)");
        $db->exec("INSERT INTO PCNFCAN VALUES (30, 'Erro'), (30, 'Outro motivo')");
        $db->exec("INSERT INTO PCFILIAL VALUES ('1', 'N')");
        $db->exec('INSERT INTO PCNFSAID VALUES (10, 9001)');
        $db->exec("INSERT INTO PCPREST VALUES (10, '1', 200, 120, '2026-10-01', NULL, 'BOL', '1', NULL, NULL, '2026-09-01'), (11, '1', 100, 80, '2026-10-02', NULL, 'BOL', '1', NULL, NULL, '2026-09-01'), (12, '1', 200, 100, '2026-10-31', NULL, 'BOL', '1', NULL, NULL, '2026-09-01'), (13, '1', 300, 900, '2026-10-01', NULL, 'BOL', '1', NULL, NULL, '2026-09-01'), (14, '1', 200, 900, '2026-10-01', NULL, 'BOL', '1', NULL, '2026-10-02', '2026-09-01'), (15, '1', 200, 900, '2026-10-01', NULL, 'BOL', '1', '2026-10-02', NULL, '2026-09-01')");

        return new ResellerRepository(new Statement($connection, $dispatcher));
    }

    public function testAgencyScopesTotalsCancellationDeduplicationAndOpenDebts(): void
    {
        $profile = $this->repository()->get(new GetResellerProfileInput('100', '2026-10-01', '2026-10-09'));
        self::assertSame(2, $profile->activity->soldOrders);
        self::assertSame('1500.00', $profile->activity->salesAmount);
        self::assertSame(2, $profile->activity->linkedCustomers);
        self::assertSame(1, $profile->activity->activeCustomers);
        self::assertSame(2, $profile->activity->cancelledOrders);
        self::assertSame('400.00', $profile->activity->cancelledAmount);
        self::assertSame('50.00', $profile->activity->returnedAmount);
        self::assertSame('100.00', $profile->cancellations->items[0]->amount);
        self::assertSame('invoice', $profile->cancellations->items[0]->source);
        self::assertSame('2026-10-04', $profile->cancellations->items[0]->cancelledAt);
        self::assertSame(3, $profile->debtSummary->openTitles);
        self::assertSame('300.00', $profile->debtSummary->openAmount);
        self::assertSame('120.00', $profile->debtSummary->linkedOverdueAmount);
        self::assertSame('80.00', $profile->debtSummary->ownOverdueAmount);
        self::assertSame(8, $profile->debtSummary->maxLateDays);
        self::assertFalse($profile->debts->items[0]->own);
        self::assertSame('9001', $profile->debts->items[0]->invoice);
        self::assertTrue($profile->debts->items[1]->own);
        self::assertNull($profile->debts->items[1]->invoice);
        self::assertSame('2026-10', $profile->monthly[0]->month);
    }

    public function testLinkedCustomerMustBeQueriedThroughItsPrincipal(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('revenda 100');
        $this->repository()->get(new GetResellerProfileInput('200', '2026-10-01', '2026-10-09'));
    }
}
