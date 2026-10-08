<?php

declare(strict_types=1);

namespace App\Tests\Functional\Enterprise;

use App\Command\Enterprise\ConfigureEnterpriseCommand;
use App\Command\User\CreateAdminCommand;
use App\Entity\User\User;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class EnterpriseWorkflowTest extends WebTestCase
{
    public function testConfigurationRequiresAdminAndRejectsInvalidPrefix(): void
    {
        $browser = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $db = $em->getConnection();

        if (false === str_ends_with((string) $db->getDatabase(), '_test')) {
            throw new RuntimeException('Dedicated *_test database required.');
        }

        $db->executeStatement('TRUNCATE enterprise, commission_rule, payment_link, order_item, order_snapshot, adjustment, commission, audit_event, app_user RESTART IDENTITY CASCADE');
        $admin = (new User())->setName('Admin')->setEmail('enterprise-admin@example.test')->setPassword('unused')->setRoles(['ROLE_ADMIN']);
        $operator = (new User())->setName('Operator')->setEmail('enterprise-operator@example.test')->setPassword('unused');
        $em->persist($admin);
        $em->persist($operator);
        $em->flush();

        $browser->request('GET', '/api/settings/enterprise');
        self::assertResponseStatusCodeSame(401);
        $browser->loginUser($operator);
        $browser->request('GET', '/api/settings/enterprise');
        self::assertResponseIsSuccessful();
        self::assertFalse(json_decode($browser->getResponse()->getContent(), true)['configured']);
        $browser->request('GET', '/api/csrf');
        $csrf = json_decode($browser->getResponse()->getContent(), true)['csrfToken'];
        $payload = ['legalName' => 'Empresa Ltda', 'tradeName' => 'Empresa', 'commissionPrefix' => 'EMP'];
        $request = static function (array $body) use ($browser, $csrf): void {
            $browser->request('PUT', '/api/settings/enterprise', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf], content: json_encode($body, \JSON_THROW_ON_ERROR));
        };
        $request($payload);
        self::assertResponseStatusCodeSame(403);
        $browser->loginUser($admin);
        $browser->request('GET', '/api/csrf');
        $csrf = json_decode($browser->getResponse()->getContent(), true)['csrfToken'];
        foreach (['', 'with space', 'lowercase', str_repeat('A', 21)] as $invalid) {
            $browser->request('PUT', '/api/settings/enterprise', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf], content: json_encode([...$payload, 'commissionPrefix' => $invalid], \JSON_THROW_ON_ERROR));
            self::assertResponseStatusCodeSame(422);
        }

        $browser->request('PUT', '/api/settings/enterprise', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf], content: json_encode($payload, \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $value = json_decode($browser->getResponse()->getContent(), true);
        self::assertTrue($value['configured']);
        self::assertSame('EMP', $value['commissionPrefix']);
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM enterprise'));
        self::assertSame('enterprise.updated', $db->fetchOne('SELECT action FROM audit_event'));

        foreach ([ConfigureEnterpriseCommand::class, CreateAdminCommand::class] as $class) {
            $command = self::getContainer()->get($class);
            $application = new Application();
            $application->addCommand($command);
            $tester = new CommandTester($command);
            self::assertSame(0, $tester->execute(['--if-missing' => true], ['interactive' => false]));
            self::assertStringContainsString('preservad', $tester->getDisplay());
        }
        self::assertTrue(self::getContainer()->get(UserRepository::class)->hasAdministrator());
        $db->executeStatement('TRUNCATE enterprise');
    }
}
