<?php

declare(strict_types=1);

namespace App\Tests\Functional\CommissionRule;

use App\Entity\CommissionRule\CommissionRule;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CommissionRuleHistoryTest extends WebTestCase
{
    public function testCurrentAndHistoryReadLegacyRulesWithoutChangingTheirSettings(): void
    {
        $browser = self::createClient();
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $database = $manager->getConnection();

        if (false === str_ends_with((string) $database->getDatabase(), '_test')) {
            throw new RuntimeException('Functional tests require a dedicated *_test database.');
        }

        $database->executeStatement('TRUNCATE commission_rule, payment_link, order_item, order_snapshot, adjustment, commission, audit_event, app_user RESTART IDENTITY CASCADE');
        $admin = (new User())->setName('Administrador')->setEmail('admin@example.test')->setRoles(['ROLE_ADMIN'])->setPassword('unused-test-password');
        $legacySettings = ['percentage' => '80.0000', 'basis' => 'margin_psd', 'psdRegion' => 1, 'subtractFreight' => true, 'applyReferenceDiscount' => false];
        $legacy = new CommissionRule($legacySettings, 'Regra inicial preservada', null);
        $manager->persist($admin);
        $manager->persist($legacy);
        $manager->flush();
        $legacyId = $legacy->view()['version'];
        $browser->loginUser($admin);
        $browser->request('GET', '/api/settings/commission-calculation');
        self::assertResponseIsSuccessful();
        $current = json_decode($browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($legacyId, $current['version']);
        self::assertSame(1, $current['psdRegion']);
        foreach (['priceContexts', 'atgPercentage', 'returnPercentage', 'atgReturnPercentage'] as $field) {
            self::assertNull($current[$field]);
        }

        $modernSettings = ['percentage' => '60.0000', 'basis' => 'margin_psd', 'priceContexts' => [['branch' => '*', 'orderRegion' => 2, 'psdRegion' => 1, 'pscfRegion' => 2]], 'atgPercentage' => '70.0000', 'returnPercentage' => '80.0000', 'atgReturnPercentage' => '100.0000', 'subtractFreight' => true, 'applyReferenceDiscount' => false];
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $modern = new CommissionRule($modernSettings, 'Nova regra por filial e tabela', null);
        $manager->persist($modern);
        $manager->flush();
        $modernId = $modern->view()['version'];
        $browser->request('GET', '/api/settings/commission-calculation');
        self::assertResponseIsSuccessful();
        $current = json_decode($browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($modernId, $current['version']);
        self::assertSame($modernSettings['priceContexts'], $current['priceContexts']);
        self::assertSame('70.0000', $current['atgPercentage']);

        $browser->request('GET', '/api/settings/commission-calculation/history');
        self::assertResponseIsSuccessful();
        $history = json_decode($browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(2, $history['total']);
        self::assertSame($current, $history['items'][0]);
        self::assertSame($legacyId, $history['items'][1]['version']);
        self::assertSame(1, $history['items'][1]['psdRegion']);
        self::assertNull($history['items'][1]['priceContexts']);
        self::assertNull($history['items'][1]['returnPercentage']);

        $database = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame($legacySettings, json_decode($database->fetchOne('SELECT settings FROM commission_rule WHERE id = :id', ['id' => $legacyId]), true, flags: \JSON_THROW_ON_ERROR));
        self::assertSame($modernSettings, json_decode($database->fetchOne('SELECT settings FROM commission_rule WHERE id = :id', ['id' => $modernId]), true, flags: \JSON_THROW_ON_ERROR));
        self::assertSame(0, (int) $database->fetchOne('SELECT COUNT(*) FROM audit_event'));
    }
}
