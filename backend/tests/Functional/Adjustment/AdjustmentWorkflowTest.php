<?php

declare(strict_types=1);

namespace App\Tests\Functional\Adjustment;

use App\Entity\Adjustment\Adjustment;
use App\Entity\Commission\Commission;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdjustmentWorkflowTest extends WebTestCase
{
    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        if (false === str_ends_with((string) $connection->getDatabase(), '_test')) {
            throw new RuntimeException('Functional tests require a dedicated *_test database.');
        }

        $connection->executeStatement('TRUNCATE commission_rule, payment_link, order_item, order_snapshot, adjustment, commission, audit_event, app_user RESTART IDENTITY CASCADE');
        $actor = (new User())->setName('Operator')->setEmail('operator@example.test')->setPassword('unused')->setRoles(['ROLE_OPERATOR']);
        $entityManager->persist($actor);
        $entityManager->flush();
        $this->browser->loginUser($actor);
    }

    public function testCreationNormalizesInputAndRecordsAudit(): void
    {
        $result = $this->createAdjustment(['customerCode' => ' 100 ', 'amount' => ' 10.125 ', 'reason' => ' Débito conferido pelo operador ']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('100', $result['customerCode']);
        self::assertSame('10.13', $result['amount']);
        self::assertSame('Débito conferido pelo operador', $result['reason']);
        self::assertNull($result['commissionId']);
        self::assertNull($result['sourceSnapshot']);
        self::assertArrayHasKey('createdAt', $result);

        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $audit = $connection->fetchAssociative("SELECT action, subject, details FROM audit_event WHERE action = 'adjustment.created'");
        self::assertSame('customer:100', $audit['subject']);
        self::assertSame(['type' => 'debt', 'amount' => '10.13', 'reference' => 'DOC-123'], json_decode($audit['details'], true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testInvalidInputDoesNotPersistAdjustmentsOrAudit(): void
    {
        foreach ([
            ['type' => 'cancellation'],
            ['customerCode' => 'ABC'],
            ['customerCode' => 100],
            ['amount' => '0.004'],
            ['amount' => '-1'],
            ['amount' => '10,00'],
            ['amount' => []],
            ['reason' => 'Curto'],
            ['reason' => str_repeat('a', 2001)],
            ['sourceReference' => ''],
        ] as $invalid) {
            $this->createAdjustment($invalid);
            self::assertResponseStatusCodeSame(422);
        }

        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM adjustment'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM audit_event'));
    }

    public function testListingPreservesPaginationCustomerAndAvailabilityFilters(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $actor = $entityManager->getRepository(User::class)->findOneBy(['email' => 'operator@example.test']);
        $commission = (new Commission())
            ->setCode('TEST-001')
            ->setCustomerCode('100')
            ->setGrossAmount('20.00')
            ->setDeductions('10.00')
            ->setNetAmount('10.00')
            ->setCreatedBy($actor);
        $entityManager->persist($commission);

        for ($index = 0; $index < 32; ++$index) {
            $adjustment = (new Adjustment())
                ->setCustomerCode(31 === $index ? '200' : '100')
                ->setAmount('10.00')
                ->setReason('Débito conferido pelo operador')
                ->setSourceReference('DOC-'.$index)
                ->setCreatedBy($actor);

            if (0 === $index) {
                $adjustment->setCommission($commission);
            }

            $entityManager->persist($adjustment);
        }

        $entityManager->flush();
        $result = $this->listAdjustments('?customer=100&page=0');
        self::assertResponseIsSuccessful();
        self::assertSame(31, $result['total']);
        self::assertSame(1, $result['page']);
        self::assertCount(30, $result['items']);
        self::assertSame(31, $result['items'][0]['id']);

        $secondPage = $this->listAdjustments('?customer=100&page=2');
        self::assertCount(1, $secondPage['items']);
        self::assertSame(1, $secondPage['items'][0]['id']);
        self::assertSame($commission->getId(), $secondPage['items'][0]['commissionId']);

        $available = $this->listAdjustments('?customer=100&available=true');
        self::assertSame(30, $available['total']);

        foreach ($available['items'] as $item) {
            self::assertNull($item['commissionId']);
        }

        $all = $this->listAdjustments('?customer=&available=false');
        self::assertSame(32, $all['total']);
    }

    public function testAuditorCanListButCannotCreate(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $auditor = (new User())->setName('Auditor')->setEmail('auditor@example.test')->setPassword('unused')->setRoles(['ROLE_AUDITOR']);
        $entityManager->persist($auditor);
        $entityManager->flush();
        $this->browser->loginUser($auditor);

        $this->listAdjustments();
        self::assertResponseIsSuccessful();
        $this->createAdjustment();
        self::assertResponseStatusCodeSame(403);
    }

    private function createAdjustment(array $overrides = []): array
    {
        $this->browser->request('GET', '/api/csrf');
        $csrf = json_decode($this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['csrfToken'];
        $payload = array_replace([
            'customerCode' => '100',
            'type' => 'debt',
            'amount' => '10.00',
            'reason' => 'Débito conferido pelo operador',
            'sourceReference' => 'DOC-123',
        ], $overrides);
        $this->browser->request('POST', '/api/adjustments', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        return json_decode($this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function listAdjustments(string $query = ''): array
    {
        $this->browser->request('GET', '/api/adjustments'.$query);

        return json_decode($this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
