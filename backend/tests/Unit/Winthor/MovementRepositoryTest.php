<?php

declare(strict_types=1);

namespace App\Tests\Unit\Winthor;

use App\Database\Connection\DatabaseConnection;
use App\Database\Statement\Statement;
use App\Repository\Winthor\MovementRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class MovementRepositoryTest extends TestCase
{
    public function testOverdueReadsInvoicesFromSalesAndKeepsTitlesWithoutAnInvoice(): void
    {
        $dispatcher = new EventDispatcher();
        $connection = new DatabaseConnection('sqlite::memory:', '', '', $dispatcher);
        $db = new class('sqlite::memory:') extends PDO {
            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                // Adapt Oracle date literals and its clock to SQLite; execute the repository query unchanged otherwise.
                $query = str_replace(["DATE '2000-01-01'", 'SYSDATE'], ["'2000-01-01'", "'2026-10-08'"], $query);

                return parent::prepare($query, $options);
            }
        };
        (new ReflectionProperty(DatabaseConnection::class, 'pdo'))->setValue($connection, $db);
        $db->sqliteCreateFunction('NVL', static fn ($value, $fallback) => $value ?? $fallback, 2);
        $db->sqliteCreateFunction('TRUNC', static fn (?string $date): ?string => $date, 1);
        $db->sqliteCreateFunction('TO_CHAR', static fn (?string $date, string $format): ?string => $date, 2);
        $db->sqliteCreateFunction('F_QTDIASVENCIDOS', static fn (string $due, string $today, string $collection, string $branch, string $businessDays, string $mode): int => (int) ((strtotime($today) - strtotime($due)) / 86400), 6);
        $db->exec('CREATE TABLE PCCLIENT (CODCLI INTEGER, CODREVENDA INTEGER, CLIENTE TEXT)');
        $db->exec('CREATE TABLE PCPREST (NUMTRANSVENDA INTEGER, PREST TEXT, CODCLI INTEGER, CODFILIAL TEXT, CODCOB TEXT, VALOR TEXT, DTVENC TEXT, DTVENCORIG TEXT, DTRECEBIMENTOPREVISTO TEXT, DTPAG TEXT, DTCANCEL TEXT, DTEMISSAO TEXT)');
        $db->exec('CREATE TABLE PCNFSAID (NUMTRANSVENDA INTEGER, NUMNOTA INTEGER)');
        $db->exec('CREATE TABLE PCCOB (CODCOB TEXT, BOLETO TEXT)');
        $db->exec('CREATE TABLE PCFILIAL (CODIGO TEXT, USADIAUTILFILIAL TEXT)');
        $db->exec("INSERT INTO PCCLIENT VALUES (200, 100, 'Vinculado'), (100, NULL, 'Principal'), (300, 999, 'Outro')");
        $db->exec("INSERT INTO PCCOB VALUES ('BOL', 'S')");
        $db->exec("INSERT INTO PCFILIAL VALUES ('1', 'N')");
        $db->exec('INSERT INTO PCNFSAID VALUES (1, 9876)');
        $db->exec("INSERT INTO PCPREST VALUES
            (1, '1', 200, '1', 'BOL', '120.00', '2026-10-01', '2026-10-01', NULL, NULL, NULL, '2026-09-01'),
            (2, '1', 100, '1', 'BOL', '80.00', '2026-10-02', '2026-10-02', NULL, NULL, NULL, '2026-09-01'),
            (3, '1', 300, '1', 'BOL', '50.00', '2026-10-01', '2026-10-01', NULL, NULL, NULL, '2026-09-01'),
            (4, '1', 200, '1', 'BOL', '50.00', '2026-10-01', '2026-10-01', NULL, '2026-10-02', NULL, '2026-09-01'),
            (5, '1', 200, '1', 'BOL', '50.00', '2026-10-09', '2026-10-09', NULL, NULL, NULL, '2026-09-01'),
            (6, '1', 200, '1', 'BOL', '50.00', '2026-10-01', '2026-10-01', NULL, NULL, '2026-10-02', '2026-09-01')");
        $repository = new MovementRepository(new Statement($connection, $dispatcher));

        $rows = $repository->overdue('100');

        self::assertCount(2, $rows);
        self::assertSame(9876, $rows[0]['NUMNOTA']);
        self::assertSame(200, $rows[0]['CODCLI']);
        self::assertNull($rows[1]['NUMNOTA']);
        self::assertSame(100, $rows[1]['CODCLI']);
    }
}
