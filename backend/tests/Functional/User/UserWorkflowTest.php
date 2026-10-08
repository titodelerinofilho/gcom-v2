<?php

declare(strict_types=1);

namespace App\Tests\Functional\User;

use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserWorkflowTest extends WebTestCase
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
        $admin = (new User())->setName('Admin')->setEmail('admin@example.test')->setPassword('unused')->setRoles(['ROLE_ADMIN']);
        $entityManager->persist($admin);
        $entityManager->flush();
        $this->browser->loginUser($admin);
    }

    public function testCreatingAndUpdatingUsersPreservesPasswordAndAudit(): void
    {
        $created = $this->write('POST', '/api/users', $this->payload());
        self::assertResponseStatusCodeSame(201);
        self::assertSame('operator@example.test', $created['email']);
        self::assertSame(['ROLE_OPERATOR', 'ROLE_USER'], $created['roles']);
        self::assertArrayNotHasKey('password', $created);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->find(User::class, $created['id']);
        $hash = $user->getPassword();
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'test-password-123'));

        $updated = $this->write('PATCH', '/api/users/'.$created['id'], ['name' => 'New operator', 'email' => 'operator@example.test', 'roles' => ['ROLE_OPERATOR'], 'password' => '']);
        self::assertResponseIsSuccessful();
        self::assertSame('New operator', $updated['name']);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame($hash, $entityManager->find(User::class, $created['id'])->getPassword());
        $actions = $entityManager->getConnection()->fetchFirstColumn('SELECT action FROM audit_event ORDER BY id');
        self::assertSame(['user.created', 'user.updated'], $actions);

        $this->browser->request('GET', '/api/users');
        self::assertResponseIsSuccessful();
        $listed = json_decode($this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(2, $listed['total']);
        self::assertSame('New operator', $listed['items'][0]['name']);
    }

    public function testInvalidUserInputCannotCreateAccounts(): void
    {
        foreach ([
            ['email' => 'invalid'],
            ['roles' => []],
            ['roles' => ['ROLE_UNKNOWN']],
            ['roles' => [123]],
            ['active' => 'false'],
            ['password' => 'short'],
            ['password' => null],
        ] as $invalid) {
            $this->write('POST', '/api/users', array_replace($this->payload(), $invalid));
            self::assertResponseStatusCodeSame(422);
        }

        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM app_user'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM audit_event'));
    }

    private function payload(): array
    {
        return ['name' => 'Operator', 'email' => 'OPERATOR@example.test', 'roles' => ['ROLE_OPERATOR'], 'password' => 'test-password-123'];
    }

    private function write(string $method, string $path, array $payload): array
    {
        $this->browser->request('GET', '/api/csrf');
        $csrf = json_decode($this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['csrfToken'];
        $this->browser->request($method, $path, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        return json_decode($this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
