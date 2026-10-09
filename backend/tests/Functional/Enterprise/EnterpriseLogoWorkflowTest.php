<?php

declare(strict_types=1);

namespace App\Tests\Functional\Enterprise;

use App\Dto\Report\Output\ReportExportContext;
use App\Dto\Reseller\Input\GetResellerProfileInput;
use App\Entity\User\User;
use App\Service\Enterprise\GetEnterpriseLogoService;
use App\Service\Report\ReportPdfTemplateService;
use App\Service\Reseller\GetResellerProfileService;
use App\Service\Reseller\ResellerPdfTemplateService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class EnterpriseLogoWorkflowTest extends WebTestCase
{
    public function testLogoLifecyclePermissionsValidationAndPdfBranding(): void
    {
        $browser = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $db = $em->getConnection();

        if (false === str_ends_with((string) $db->getDatabase(), '_test')) {
            throw new RuntimeException('Dedicated *_test database required.');
        }

        $db->executeStatement('TRUNCATE enterprise, commission_rule, payment_link, order_item, order_snapshot, adjustment, commission, audit_event, app_user RESTART IDENTITY CASCADE');
        $admin = (new User())->setName('Admin')->setEmail('logos-admin@example.test')->setPassword('unused')->setRoles(['ROLE_ADMIN']);
        $operator = (new User())->setName('Operator')->setEmail('logos-operator@example.test')->setPassword('unused');
        $em->persist($admin);
        $em->persist($operator);
        $em->flush();
        $browser->request('GET', '/api/settings/enterprise/logo');
        self::assertResponseStatusCodeSame(401);
        $browser->loginUser($admin);
        $browser->request('GET', '/api/csrf');
        $csrf = json_decode($browser->getResponse()->getContent(), true)['csrfToken'];
        $upload = static function (string $contents, string $name = 'logo.png') use ($browser, $csrf): void {
            $path = tempnam(sys_get_temp_dir(), 'logo-test-');
            file_put_contents($path, $contents);

            try {
                $browser->request('POST', '/api/settings/enterprise/logo', files: ['logo' => new UploadedFile($path, $name, test: true)], server: ['HTTP_X_CSRF_TOKEN' => $csrf]);
            } finally {
                if (true === is_file($path)) {
                    unlink($path);
                }
            }
        };
        $png = file_get_contents(__DIR__.'/../../../public/logo-gcom.png');
        $upload($png);
        self::assertResponseStatusCodeSame(409);
        $browser->request('PUT', '/api/settings/enterprise', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf], content: json_encode(['legalName' => 'Empresa', 'tradeName' => 'Empresa', 'commissionPrefix' => 'EMP'], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $upload('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'logo.svg');
        self::assertResponseStatusCodeSame(422);
        $upload($png.str_repeat('x', 1048576));
        self::assertResponseStatusCodeSame(422);
        $browser->request('POST', '/api/settings/enterprise/logo', server: ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseStatusCodeSame(422);
        $browser->request('POST', '/api/settings/enterprise/logo', files: [], server: ['HTTP_X_CSRF_TOKEN' => 'wrong']);
        self::assertResponseStatusCodeSame(403);
        $upload($png, '../../arbitrary.php');
        self::assertResponseIsSuccessful();
        $value = json_decode($browser->getResponse()->getContent(), true);
        self::assertStringStartsWith('/api/settings/enterprise/logo?v=', $value['logoUrl']);
        $firstPath = self::getContainer()->get(GetEnterpriseLogoService::class)->path();
        self::assertSame($png, file_get_contents($firstPath));
        self::assertStringEndsWith('.png', $firstPath);
        self::assertSame('data:image/png;base64,'.base64_encode($png), self::getContainer()->get(GetEnterpriseLogoService::class)->dataUri());
        $browser->request('GET', '/api/settings/enterprise/logo');
        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $browser->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('inline', $browser->getResponse()->headers->get('Content-Disposition'));
        $upload($png);
        self::assertResponseIsSuccessful();
        self::assertFileDoesNotExist($firstPath);
        $secondPath = self::getContainer()->get(GetEnterpriseLogoService::class)->path();
        self::assertNotSame($firstPath, $secondPath);
        self::assertSame(2, (int) $db->fetchOne("SELECT COUNT(*) FROM audit_event WHERE action = 'enterprise.logo_uploaded'"));
        $context = new ReportExportContext('/api/reports/commissions', '2026-10-01', '2026-10-09', [], 'commissions', [], 'Relatório', 'Critérios', generatedAt: '2026-10-09T12:00:00-03:00');
        $reportHtml = self::getContainer()->get(ReportPdfTemplateService::class)->render($context, []);
        self::assertStringContainsString('alt="GCOM"', $reportHtml);
        self::assertStringContainsString('alt="Logo da empresa"', $reportHtml);
        $profile = self::getContainer()->get(GetResellerProfileService::class)->get(new GetResellerProfileInput('100', '2026-10-01', '2026-10-09'));
        $profileHtml = self::getContainer()->get(ResellerPdfTemplateService::class)->render($profile, 'Empresa');
        self::assertStringContainsString('alt="GCOM"', $profileHtml);
        self::assertStringContainsString('alt="Logo da empresa"', $profileHtml);
        $browser->loginUser($operator);
        $browser->request('GET', '/api/csrf');
        $operatorCsrf = json_decode($browser->getResponse()->getContent(), true)['csrfToken'];
        $browser->request('DELETE', '/api/settings/enterprise/logo', server: ['HTTP_X_CSRF_TOKEN' => $operatorCsrf]);
        self::assertResponseStatusCodeSame(403);
        self::assertFileExists($secondPath);
        $browser->loginUser($admin);
        $browser->request('GET', '/api/csrf');
        $adminCsrf = json_decode($browser->getResponse()->getContent(), true)['csrfToken'];
        $browser->request('DELETE', '/api/settings/enterprise/logo', server: ['HTTP_X_CSRF_TOKEN' => $adminCsrf]);
        self::assertResponseIsSuccessful();
        self::assertNull(json_decode($browser->getResponse()->getContent(), true)['logoUrl']);
        self::assertFileDoesNotExist($secondPath);
        self::assertNull(self::getContainer()->get(GetEnterpriseLogoService::class)->dataUri());
        self::assertSame(1, (int) $db->fetchOne("SELECT COUNT(*) FROM audit_event WHERE action = 'enterprise.logo_removed'"));
        $browser->request('GET', '/api/settings/enterprise/logo');
        self::assertResponseStatusCodeSame(404);
    }
}
