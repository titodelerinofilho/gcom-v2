<?php

declare(strict_types=1);

namespace App\Tests\Functional\Reseller;

use App\Entity\User\User;
use App\Tests\Double\Winthor\InMemoryResellerGateway;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ResellerProfileWorkflowTest extends WebTestCase
{
    private KernelBrowser $browser;

    protected function setUp(): void
    {
        InMemoryResellerGateway::$unavailable = false;
        InMemoryResellerGateway::$debtTotal = 2;
        $this->browser = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $db = $em->getConnection();

        if (false === str_ends_with((string) $db->getDatabase(), '_test')) {
            throw new RuntimeException('Dedicated *_test database required.');
        }

        $db->executeStatement('TRUNCATE enterprise, commission_rule, payment_link, order_item, order_snapshot, adjustment, commission, audit_event, app_user RESTART IDENTITY CASCADE');
        $user = new User()->setName('Operator')->setEmail('reseller@example.test')->setPassword('unused')->setRoles(['ROLE_OPERATOR']);
        $em->persist($user);
        $em->flush();
        $this->browser->loginUser($user);

        for ($index = 1; $index <= 10; ++$index) {
            $mode = 8 === $index ? 'atg' : 'normal';
            $customer = 9 === $index ? '999' : '100';
            $status = 10 === $index ? 'rejected' : 'paid';
            $id = $db->fetchOne("INSERT INTO commission (code, customer_code, customer_name, gross_amount, deductions, net_amount, status, calculation, created_at, created_by_id, approved_by_id, approved_at, rejected_by_id, rejected_at, rejection_reason) VALUES (:code, :customer, 'Revenda', 120, 20, 100, :status, :calculation, :created, 1, 1, '2025-01-01', :rejected_by, :rejected_at, :rejection_reason) RETURNING id", ['code' => 'TEST-'.$index, 'customer' => $customer, 'status' => $status, 'calculation' => json_encode(['mode' => $mode]), 'created' => 10 === $index ? '2026-10-04' : '2025-01-01', 'rejected_by' => 10 === $index ? 1 : null, 'rejected_at' => 10 === $index ? '2026-10-04' : null, 'rejection_reason' => 10 === $index ? 'Reprovada para teste' : null]);

            if (10 === $index) {
                continue;
            }

            $db->executeStatement("INSERT INTO payment_link (commission_id, amount, calculated_amount, manual_amount, manual_reason, paid_at, confirmed_at, confirmed_by_id, verification, notes) VALUES (:id, 91.50, 100, true, 'Valor efetivamente pago', :date, CURRENT_TIMESTAMP, 1, 'none', '')", ['id' => $id, 'date' => 7 >= $index ? '2026-10-0'.$index : '2026-10-08']);
        }
        $id = $db->fetchOne("INSERT INTO commission (code, customer_code, customer_name, gross_amount, deductions, net_amount, status, calculation, created_at, created_by_id, approved_by_id, approved_at) VALUES ('NEW', '100', 'Revenda', 100, 20, 80, 'approved', '{\"mode\":\"normal\"}', '2026-10-03', 1, 1, '2026-10-03') RETURNING id");
        $db->executeStatement("INSERT INTO adjustment (customer_code, type, amount, reason, source_reference, commission_id, created_at, created_by_id) VALUES ('100', 'return', 15, 'Devolução deduzida', 'NUMTRANSENT 500', :id, '2025-01-01', 1), ('100', 'debt', 5, 'Débito', 'D1', :id, '2026-10-03', 1), ('100', 'return', 99, 'Pendente', 'D2', NULL, '2026-10-03', 1)", ['id' => $id]);
    }

    public function testPaidPaginationActualAmountsAndDifferentDateBasesArePreserved(): void
    {
        $this->browser->request('GET', '/api/resellers/profile?customer=100&from=2026-10-01&to=2026-10-09');
        self::assertResponseIsSuccessful();
        $profile = json_decode($this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(7, $profile['paidCommissions']['total']);
        self::assertCount(5, $profile['paidCommissions']['items']);
        self::assertSame('TEST-7', $profile['paidCommissions']['items'][0]['code']);
        self::assertEquals('640.50', $profile['finance']['paidAmount']);
        self::assertSame(1, $profile['finance']['generatedCount']);
        self::assertEquals('80.00', $profile['finance']['pendingAmount']);
        self::assertEquals('15.00', $profile['finance']['appliedReturnsAmount']);
        self::assertSame(1, $profile['appliedReturns']['total']);
        self::assertSame('NUMTRANSENT 500', $profile['appliedReturns']['items'][0]['reference']);
        self::assertEquals('640.50', $profile['monthly'][0]['paidAmount']);
        $this->browser->request('GET', '/api/resellers/profile?customer=100&from=2026-10-01&to=2026-10-09&paidPage=2');
        self::assertResponseIsSuccessful();
        $next = json_decode($this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(2, $next['paidCommissions']['items']);
        self::assertSame('TEST-2', $next['paidCommissions']['items'][0]['code']);
    }

    public function testInvalidDatesIdentifiersAndPaginationAreRejected(): void
    {
        foreach (['customer=0', 'customer=100&from=invalid', 'customer=100&from=2026-10-09&to=2026-10-01', 'customer=100&from=2024-01-01&to=2026-10-09', 'customer=100&paidPage=0', 'customer=100&debtsPage=-1'] as $query) {
            $this->browser->request('GET', '/api/resellers/profile?'.$query);
            self::assertResponseStatusCodeSame(422);
        }
        $this->browser->request('GET', '/api/resellers/profile?customer=999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testOracleOutageDoesNotProduceAnEmptyPortfolioOrRating(): void
    {
        InMemoryResellerGateway::$unavailable = true;
        $this->browser->request('GET', '/api/resellers/profile?customer=100');
        self::assertResponseStatusCodeSame(503);
        $this->browser->request('GET', '/api/resellers/profile.pdf?customer=100');
        self::assertResponseStatusCodeSame(503);
    }

    public function testPdfIgnoresScreenPaginationIsPrivateAndAudited(): void
    {
        $this->browser->request('GET', '/api/resellers/profile.pdf?customer=100&from=2026-10-01&to=2026-10-09&paidPage=99');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringContainsString('private', $this->browser->getResponse()->headers->get('Cache-Control'));
        self::assertStringStartsWith('%PDF-', $this->browser->getInternalResponse()->getContent());
        $db = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame('reseller_profile.exported', $db->fetchOne('SELECT action FROM audit_event'));
        self::assertSame(11, (int) $db->fetchOne('SELECT COUNT(*) FROM commission'));
    }

    public function testPdfRefusesToSilentlyTruncateAndAuthenticationIsRequired(): void
    {
        InMemoryResellerGateway::$debtTotal = 1001;
        $this->browser->request('GET', '/api/resellers/profile.pdf?customer=100&from=2026-10-01&to=2026-10-09');
        self::assertResponseStatusCodeSame(422);
        $this->browser->restart();
        $this->browser->request('GET', '/api/resellers/profile?customer=100');
        self::assertResponseStatusCodeSame(401);
        $this->browser->request('GET', '/api/resellers/profile.pdf?customer=100');
        self::assertResponseStatusCodeSame(401);
    }
}
