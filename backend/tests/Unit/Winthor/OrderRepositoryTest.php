<?php

declare(strict_types=1);

namespace App\Tests\Unit\Winthor;

use App\Database\Connection\DatabaseConnection;
use App\Database\Statement\Statement;
use App\Exception\Business\BusinessException;
use App\Repository\Winthor\OrderRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class OrderRepositoryTest extends TestCase
{
    private function repository(): OrderRepository
    {
        $dispatcher = new EventDispatcher();
        $connection = new DatabaseConnection('sqlite::memory:', '', '', $dispatcher);
        $db = $connection->getConnection();
        $db->sqliteCreateFunction('NVL', static fn ($value, $fallback): string => (string) ($value ?? $fallback), 2);
        $db->exec('CREATE TABLE PCCLIENT (CODCLI INTEGER, CODREVENDA INTEGER, CODPRACA INTEGER)');
        $db->exec('CREATE TABLE PCPEDC (NUMPED INTEGER, CODCLI INTEGER, CODPRACA INTEGER, POSICAO TEXT, DTCANCEL TEXT, CONDVENDA INTEGER, CODCOB TEXT)');
        $db->exec('CREATE TABLE PCMOV (NUMPED INTEGER, CODOPER TEXT, DTCANCEL TEXT, QT INTEGER)');
        $db->exec('INSERT INTO PCCLIENT VALUES (200, 100, 573), (100, NULL, 573), (300, 999, 562)');
        $db->exec("INSERT INTO PCPEDC VALUES (1, 200, 562, 'F', NULL, 1, 'BOLETO'), (2, 200, 573, 'F', NULL, 1, 'BOLETO'), (3, 100, 562, 'F', NULL, 1, 'BOLETO'), (4, 300, 562, 'F', NULL, 1, 'BOLETO')");

        return new OrderRepository(new Statement($connection, $dispatcher));
    }

    public function testNormalUsesTheOrderSquareEvenWhenTheCustomerHasAnotherSquare(): void
    {
        $this->repository()->assertCommissionEligible(['1'], '100', 'normal', 562);
        $this->addToAssertionCount(1);
    }

    public function testNormalCannotAcceptPsdOrdersEvenIfLinkedToThePrincipal(): void
    {
        $this->expectException(BusinessException::class);
        $this->repository()->assertCommissionEligible(['2'], '100', 'normal', 573);
    }

    public function testAtgAcceptsPsdOrders(): void
    {
        $this->repository()->assertCommissionEligible(['2'], '100', 'atg', 573);
        $this->addToAssertionCount(1);
    }

    public function testNormalRequiresCodrevendaAndCannotUseTheCodcliFallback(): void
    {
        $this->expectException(BusinessException::class);
        $this->repository()->assertCommissionEligible(['3'], '100', 'normal', 562);
    }

    public function testDifferentOrderSquareIsRejected(): void
    {
        $this->expectException(BusinessException::class);
        $this->repository()->assertCommissionEligible(['1'], '100', 'normal', 563);
    }

    public function testDifferentPrincipalIsRejected(): void
    {
        $this->expectException(BusinessException::class);
        $this->repository()->assertCommissionEligible(['4'], '100', 'normal', 562);
    }
}
