<?php

declare(strict_types=1);

namespace App\Tests\Functional\Commission;

use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use ZipArchive;

final class CommissionWorkflowTest extends WebTestCase
{
    private KernelBrowser $browser;

    protected function setUp(): void
    {
        \App\Tests\Double\Winthor\InMemoryMovementGateway::$cancelled = [];
        \App\Tests\Double\Winthor\InMemoryMovementGateway::$returnRows = [];
        $this->browser = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $db = $em->getConnection();

        // This suite deliberately resets only a database whose name ends in _test.
        if (!str_ends_with((string) $db->getDatabase(), '_test')) {
            throw new RuntimeException('Functional tests require a dedicated *_test database.');
        }
        $db->executeStatement('TRUNCATE commission_rule, payment_link, order_item, order_snapshot, adjustment, commission, audit_event, app_user RESTART IDENTITY CASCADE');
        $db->executeStatement('INSERT INTO commission_rule (settings, reason, created_at) VALUES (:settings, :reason, CURRENT_TIMESTAMP)', ['settings' => json_encode(['percentage' => '80.0000', 'basis' => 'margin_psd', 'priceContexts' => [['branch' => '*', 'orderRegion' => 2, 'psdRegion' => 1, 'pscfRegion' => 2]], 'atgPercentage' => '80', 'returnPercentage' => '80', 'atgReturnPercentage' => '100', 'subtractFreight' => true, 'applyReferenceDiscount' => false]), 'reason' => 'Regra inicial validada para testes']);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        foreach (['operator' => 'ROLE_OPERATOR', 'finance' => 'ROLE_FINANCE', 'admin' => 'ROLE_ADMIN', 'auditor' => 'ROLE_AUDITOR'] as $name => $role) {
            $u = (new User())->setName($name)->setEmail($name.'@example.test')->setRoles([$role]);
            $u->setPassword($hasher->hashPassword($u, 'test-password-123'));
            $em->persist($u);
        }
        $em->flush();
    }

    private function call(string $method, string $path, array $data = []): array
    {
        $this->browser->request('GET', '/api/csrf');
        $csrf = json_decode($this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['csrfToken'];
        $this->browser->request($method, '/api'.$path, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf], content: 'GET' === $method ? null : json_encode($data, \JSON_THROW_ON_ERROR));

        return json_decode($this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function login(string $name): void
    {
        $this->call('POST', '/login', ['email' => $name.'@example.test', 'password' => 'test-password-123']);
        self::assertResponseIsSuccessful();
    }

    private function createCommission(string $number = '123'): array
    {
        $order = $this->call('POST', '/orders/import', ['orderNumber' => $number]);
        self::assertResponseStatusCodeSame(201);
        $result = $this->call('POST', '/commissions', ['orderIds' => [$order['id']], 'ruleVersion' => 1, 'reason' => 'Memória de cálculo validada para teste']);
        self::assertResponseStatusCodeSame(201);

        return $result;
    }

    public function testHistoricalProductAllocationUsesSavedRuleWithoutWritingHistory(): void
    {
        $this->login('operator');
        $created = $this->createCommission();
        self::assertSame('normal', $created['mode']);
        self::assertSame('100.00', $created['calculation']['items'][0]['commissionAmount']);
        $snapshot = $created['calculation'];
        unset($snapshot['itemAllocationVersion'], $snapshot['percentageApplied']);
        foreach ($snapshot['items'] as &$item) {
            unset($item['commissionAmount'], $item['allocatedFreight'], $item['roundingAdjustment'], $item['commissionBeforeFreight'], $item['percentageApplied']);
        }
        unset($item);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $historical = (new \App\Entity\Commission\Commission())->setCustomerCode('100')->setCustomerName('Cliente histórico')->setGrossAmount('100.00')->setNetAmount('100.00')->setCalculation($snapshot)->setCreatedBy($em->getRepository(User::class)->findOneBy(['email' => 'operator@example.test']));
        $em->persist($historical);
        $em->flush();
        $id = $historical->getId();

        $this->login('admin');
        $this->call('POST', '/settings/commission-calculation', ['expectedVersion' => 1, 'percentage' => '60', 'basis' => 'margin_psd', 'priceContexts' => [['branch' => '*', 'orderRegion' => 2, 'psdRegion' => 1, 'pscfRegion' => 2]], 'atgPercentage' => '80', 'returnPercentage' => '80', 'atgReturnPercentage' => '100', 'subtractFreight' => true, 'applyReferenceDiscount' => false, 'reason' => 'Nova regra para conferir preservação histórica']);
        self::assertResponseStatusCodeSame(201);
        $db = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $auditCount = $db->fetchOne('SELECT COUNT(*) FROM audit_event');
        $detail = $this->call('GET', '/commissions/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSame('100.00', $detail['calculation']['items'][0]['commissionAmount']);
        self::assertSame('80.0000', $detail['calculation']['items'][0]['percentageApplied']);
        self::assertSame('100.00', $detail['grossAmount']);

        $db = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame($snapshot, json_decode($db->fetchOne('SELECT calculation FROM commission WHERE id = :id', ['id' => $id]), true, flags: \JSON_THROW_ON_ERROR));
        self::assertSame($auditCount, $db->fetchOne('SELECT COUNT(*) FROM audit_event'));
    }

    public function testAvailableOrdersRequirePrincipalAndPeriodAndExcludeCommissionedOrders(): void
    {
        $this->login('finance');
        $this->call('GET', '/winthor/orders/available?customer=100&from=2026-10-01&to=2026-10-07');
        self::assertResponseStatusCodeSame(403);

        $this->login('operator');

        foreach (['', '?customer=100', '?customer=100&from=2026-02-30&to=2026-10-07', '?customer=100&from=2026-10-07&to=2026-10-01', '?customer=100&from=2024-01-01&to=2026-10-07'] as $filters) {
            $this->call('GET', '/winthor/orders/available'.$filters);
            self::assertResponseStatusCodeSame(422);
        }

        $result = $this->call('GET', '/winthor/orders/available?customer=100&from=2026-10-01&to=2026-10-07');
        self::assertResponseIsSuccessful();
        self::assertSame('123', $result['items'][0]['orderNumber']);
        self::assertSame('900', $result['items'][0]['invoiceNumber']);
        self::assertSame('100', $result['items'][0]['customerCode']);
        self::assertSame('2026-10-01', $result['items'][0]['orderDate']);
        self::assertSame('1200.00', $result['items'][0]['total']);
        self::assertSame(2, $result['items'][0]['priceContext']['orderRegion']);
        self::assertSame(1, $result['items'][0]['priceContext']['psdRegion']);
        self::assertSame(2, $result['items'][0]['priceContext']['pscfRegion']);
        self::assertSame('PVENDA3', $result['items'][0]['priceContext']['priceColumn']);
        self::assertNull($result['items'][0]['priceContextError']);
        self::assertSame(['customer' => '100', 'from' => '2026-10-01', 'to' => '2026-10-07', 'square' => null], \App\Tests\Double\Winthor\InMemoryOrderGateway::$searchCalls[array_key_last(\App\Tests\Double\Winthor\InMemoryOrderGateway::$searchCalls)]);

        $this->call('POST', '/orders/import', ['orderNumber' => '123']);
        self::assertResponseStatusCodeSame(201);
        $available = $this->call('GET', '/winthor/orders/available?customer=100&from=2026-10-01&to=2026-10-07');
        self::assertCount(1, $available['items']);

        $this->createCommission();
        $assigned = $this->call('GET', '/winthor/orders/available?customer=100&from=2026-10-01&to=2026-10-07');
        self::assertResponseIsSuccessful();
        self::assertSame([], $assigned['items']);
    }

    public function testReturnsAndCancellationAreDeductedOnceFromTheNextCommission(): void
    {
        $this->login('operator');
        $original = $this->createCommission('123');
        \App\Tests\Double\Winthor\InMemoryMovementGateway::$returnRows = [[
            'PRINCIPAL' => '100', 'CODFILIAL' => '1', 'NUMREGIAO' => 2,
            'CODPROD' => '200', 'NUMPED' => '123', 'NUMNOTA' => '900',
            'QT' => '1', 'PUNIT' => '400', 'COMMISSION_RETURN_PRICES' => ['1' => '350'],
        ]];
        $return = $this->call('POST', '/winthor/returns/import', ['customer' => '100', 'numtransent' => '901', 'atg' => false]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('40.00', $return['amount']);
        self::assertCount(1, $return['sourceSnapshot']['items']);
        $this->call('POST', '/winthor/returns/import', ['customer' => '100', 'numtransent' => '901', 'atg' => true]);
        self::assertResponseStatusCodeSame(409);
        \App\Tests\Double\Winthor\InMemoryMovementGateway::$cancelled['123'] = [[
            'NUMPED' => '123', 'CODPROD' => '200', 'QT' => '-3', 'NUMTRANSVENDA' => '902',
        ]];
        $orders = [];
        foreach (['456', '789'] as $number) {
            $orders[] = $this->call('POST', '/orders/import', ['orderNumber' => $number])['id'];
        }
        $preview = $this->call('POST', '/commissions/preview', ['orderIds' => $orders]);
        self::assertResponseIsSuccessful();
        self::assertSame('200.00', $preview['gross']);
        self::assertSame('100.00', $preview['deductions']);
        self::assertSame('100.00', $preview['net']);
        self::assertCount(2, $preview['adjustmentIds']);
        self::assertSame(['return', 'cancellation'], array_column($preview['adjustments'], 'type'));
        $again = $this->call('POST', '/commissions/preview', ['orderIds' => $orders]);
        self::assertSame($preview['adjustmentIds'], $again['adjustmentIds']);
        $next = $this->call('POST', '/commissions', ['orderIds' => $orders, 'ruleVersion' => 1, 'expectedAdjustmentIds' => $preview['adjustmentIds'], 'reason' => 'Estorno e devolução conferidos com a operação']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('100.00', $next['netAmount']);
        $detail = $this->call('GET', '/commissions/'.$next['id']);
        self::assertCount(2, $detail['adjustments']);
        $cancel = array_values(array_filter($detail['adjustments'], static fn (array $a): bool => 'cancellation' === $a['type']))[0];
        self::assertSame('60.00', $cancel['amount']);
        $returnsReport = $this->call('GET', '/reports/adjustments?customer=100&type=return&state=deducted&mode=normal&dateBasis=applied');
        self::assertSame(1, $returnsReport['total']);
        self::assertSame('40.00', $returnsReport['amount']);
        $cancelReport = $this->call('GET', '/reports/adjustments?customer=100&type=cancellation&state=deducted');
        self::assertSame(1, $cancelReport['total']);
        self::assertSame('60.00', $cancelReport['amount']);
        self::assertSame($original['id'], $cancel['sourceSnapshot']['originalCommissionId']);
        $this->call('POST', '/winthor/returns/import', ['customer' => '100', 'numtransent' => '903', 'atg' => false]);
        self::assertResponseStatusCodeSame(409);
        $thirdOrder = $this->call('POST', '/orders/import', ['orderNumber' => '999']);
        $third = $this->call('POST', '/commissions/preview', ['orderIds' => [$thirdOrder['id']]]);
        self::assertSame('0.00', $third['deductions']);
    }

    public function testChangedDeductionsRequireAnotherPreview(): void
    {
        $this->login('operator');
        $order = $this->call('POST', '/orders/import', ['orderNumber' => '456']);
        $preview = $this->call('POST', '/commissions/preview', ['orderIds' => [$order['id']]]);
        $this->call('POST', '/adjustments', ['customerCode' => '100', 'type' => 'debt', 'amount' => '10', 'sourceReference' => 'Documento 123', 'reason' => 'Débito conferido depois da simulação']);
        $this->call('POST', '/commissions', ['orderIds' => [$order['id']], 'ruleVersion' => 1, 'expectedAdjustmentIds' => $preview['adjustmentIds'], 'reason' => 'Teste de alteração de deduções pendentes']);
        self::assertResponseStatusCodeSame(409);
        $newPreview = $this->call('POST', '/commissions/preview', ['orderIds' => [$order['id']]]);
        self::assertSame('90.00', $newPreview['net']);
    }

    public function testPartialCancellationBlocksUnreviewedDeduction(): void
    {
        $this->login('operator');
        $this->createCommission('123');
        \App\Tests\Double\Winthor\InMemoryMovementGateway::$cancelled['123'] = [['CODPROD' => '200', 'QT' => '-1']];
        $order = $this->call('POST', '/orders/import', ['orderNumber' => '456']);
        $this->call('POST', '/commissions/preview', ['orderIds' => [$order['id']]]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testAnonymousRequestsAndMissingCsrfAreRejected(): void
    {
        $this->browser->request('GET', '/api/commissions');
        self::assertResponseStatusCodeSame(401);
        $this->browser->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(403);
    }

    public function testPaymentWithoutNumtransAndLinkAfterwards(): void
    {
        $this->login('operator');
        $c = $this->createCommission();
        $snapshot = $this->call('GET', '/orders/'.$c['orders'][0]['id']);
        self::assertSame('1', $snapshot['items'][0]['raw']['NUMSEQ']);
        self::assertSame('900', $snapshot['invoiceNumber']);
        self::assertSame('100', $snapshot['authorCustomerCode']);
        self::assertSame('Cliente de Teste', $snapshot['authorCustomerName']);
        $this->login('finance');
        $this->call('POST', '/commissions/'.$c['id'].'/approve');
        self::assertResponseIsSuccessful();
        $this->call('POST', '/commissions/'.$c['id'].'/payment', ['amount' => '100.00', 'paidAt' => date('Y-m-d'), 'notes' => 'Pagamento conferido sem lançamento da rotina 749']);
        self::assertResponseIsSuccessful();
        $detail = $this->call('GET', '/commissions/'.$c['id']);
        self::assertSame('paid', $detail['status']);
        self::assertNull($detail['payment']['winthor']);
        self::assertFalse($detail['payment']['manualAmount']);
        self::assertNull($detail['payment']['manualReason']);
        self::assertSame('100.00', $detail['payment']['calculatedAmount']);
        $this->call('POST', '/commissions/'.$c['id'].'/payment/winthor', ['recnum' => '987654']);
        self::assertResponseIsSuccessful();
        $detail = $this->call('GET', '/commissions/'.$c['id']);
        self::assertSame('987654', $detail['payment']['winthor']['recnum']);
        self::assertSame('manual_reference', $detail['payment']['winthor']['verification']);
        $this->call('POST', '/commissions/'.$c['id'].'/payment/winthor', ['recnum' => '555']);
        self::assertResponseStatusCodeSame(409);
        $this->call('POST', '/commissions/'.$c['id'].'/payment', ['amount' => '100.00', 'paidAt' => date('Y-m-d'), 'notes' => 'Tentativa repetida de pagamento']);
        self::assertResponseStatusCodeSame(409);
        $this->login('auditor');
        $events = $this->call('GET', '/audit');
        self::assertContains('payment.winthor_linked', array_column($events['items'], 'action'));
    }

    public function testManualPaymentPreservesBothAmountsAndOptionalJustification(): void
    {
        $this->login('operator');
        $commission = $this->createCommission();
        $path = '/commissions/'.$commission['id'].'/payment';
        $payload = ['amount' => '90.00', 'paidAt' => date('Y-m-d'), 'notes' => 'Pagamento conferido pelo financeiro'];

        $this->call('POST', $path, $payload + ['manualAmount' => true, 'manualReason' => 'Acordo validado pelo financeiro']);
        self::assertResponseStatusCodeSame(403);

        $this->login('finance');
        $this->call('POST', '/commissions/'.$commission['id'].'/approve');

        foreach ([
            [],
            ['manualAmount' => true, 'manualReason' => str_repeat('a', 2001)],
            ['manualAmount' => 'true', 'manualReason' => 'Acordo validado pelo financeiro'],
            ['manualAmount' => false, 'manualReason' => 'Acordo validado pelo financeiro'],
        ] as $override) {
            $this->call('POST', $path, $payload + $override);
            self::assertResponseStatusCodeSame(422);
        }

        $reason = 'Acordo validado pelo financeiro';
        $this->call('POST', $path, $payload + ['manualAmount' => true, 'manualReason' => $reason]);
        self::assertResponseIsSuccessful();

        $detail = $this->call('GET', '/commissions/'.$commission['id']);
        self::assertSame('paid', $detail['status']);
        self::assertSame('100.00', $detail['netAmount']);
        self::assertSame('100.00', $detail['payment']['calculatedAmount']);
        self::assertSame('90.00', $detail['payment']['amount']);
        self::assertTrue($detail['payment']['manualAmount']);
        self::assertSame($reason, $detail['payment']['manualReason']);
        self::assertSame($commission['calculation'], $detail['calculation']);

        $this->call('POST', $path.'/winthor', ['recnum' => '987654']);
        self::assertResponseIsSuccessful();

        $report = $this->call('GET', '/reports/commissions?status=paid');
        self::assertSame('90.00', $report['items'][0]['paid_amount']);
        self::assertSame('Sim', $report['items'][0]['manual_amount']);
        $summary = $this->call('GET', '/reports/summary');
        self::assertSame('90.00', $summary['totals']['paid']);

        $this->login('auditor');
        $audit = $this->call('GET', '/audit');
        $payments = array_values(array_filter($audit['items'], static fn (array $event): bool => 'commission.paid' === $event['action']));
        self::assertSame('100.00', $payments[0]['details']['calculatedAmount']);
        self::assertSame('90.00', $payments[0]['details']['amount']);
        self::assertTrue($payments[0]['details']['manualAmount']);
        self::assertSame($reason, $payments[0]['details']['manualReason']);

        $db = self::getContainer()->get(EntityManagerInterface::class)->getConnection();

        try {
            $db->executeStatement('UPDATE payment_link SET amount = 91 WHERE commission_id = :id', ['id' => $commission['id']]);
            self::fail('Confirmed payment amounts must remain immutable');
        } catch (\Doctrine\DBAL\Exception $exception) {
            self::assertStringContainsString('immutable', $exception->getMessage());
        }
    }

    public function testFinanceCreatorCanApproveAndOrderCannotBeCommissionedTwice(): void
    {
        $this->login('admin');
        $c = $this->createCommission();
        $this->call('POST', '/commissions/'.$c['id'].'/approve');
        self::assertResponseIsSuccessful();
        self::assertSame('approved', $this->call('GET', '/commissions/'.$c['id'])['status']);
        $this->call('POST', '/commissions', ['orderIds' => [$c['orders'][0]['id']], 'ruleVersion' => 1, 'reason' => 'Tentativa de duplicação da comissão']);
        self::assertResponseStatusCodeSame(409);
    }

    public function testDeductionsAreAppliedExactlyOnceAndWrongPaymentAmountFails(): void
    {
        $this->login('operator');
        $order = $this->call('POST', '/orders/import', ['orderNumber' => '222']);
        $a = $this->call('POST', '/adjustments', ['customerCode' => '100', 'type' => 'return', 'amount' => '20.10', 'reason' => 'Devolução validada pelo operador', 'sourceReference' => 'DEV-123']);
        $c = $this->call('POST', '/commissions', ['orderIds' => [$order['id']], 'adjustmentIds' => [$a['id']], 'ruleVersion' => 1, 'reason' => 'Memória de cálculo com dedução']);
        self::assertSame('80.20', $c['netAmount']);
        $this->login('finance');
        $this->call('POST', '/commissions/'.$c['id'].'/approve');
        $this->call('POST', '/commissions/'.$c['id'].'/payment', ['amount' => '80.21', 'paidAt' => date('Y-m-d'), 'notes' => 'Valor do pagamento divergente']);
        self::assertResponseStatusCodeSame(422);
        $detail = $this->call('GET', '/commissions/'.$c['id']);
        self::assertSame('approved', $detail['status']);
        self::assertNull($detail['payment']);
    }

    public function testAuditorCannotWriteAndAdminCannotDisableOwnAccount(): void
    {
        $this->login('auditor');
        $this->call('POST', '/orders/import', ['orderNumber' => '123']);
        self::assertResponseStatusCodeSame(403);
        $this->login('admin');
        $me = $this->call('GET', '/me');
        $this->call('PATCH', '/users/'.$me['id'], ['name' => 'admin', 'email' => 'admin@example.test', 'roles' => ['ROLE_ADMIN'], 'active' => false]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testHistoryCannotBeChangedAtDatabaseLevel(): void
    {
        $this->login('operator');
        $c = $this->createCommission();
        $db = self::getContainer()->get(EntityManagerInterface::class)->getConnection();

        try {
            $db->executeStatement('UPDATE order_snapshot SET total = 0 WHERE id = :id', ['id' => $c['orders'][0]['id']]);
            self::fail('Snapshot update should fail');
        } catch (\Doctrine\DBAL\Exception $e) {
            self::assertStringContainsString('immutable', $e->getMessage());
        }

        try {
            $db->executeStatement('DELETE FROM audit_event');
            self::fail('Audit deletion should fail');
        } catch (\Doctrine\DBAL\Exception $e) {
            self::assertStringContainsString('immutable', $e->getMessage());
        }
    }

    public function testPaidAtgReportsAndDebtAbatementsUseTheSameFiltersInExports(): void
    {
        $this->login('operator');
        $debt = $this->call('POST', '/adjustments', ['customerCode' => '100', 'type' => 'debt', 'amount' => '25', 'sourceReference' => 'Documento financeiro 123', 'reason' => 'Débito para abatimento na próxima comissão']);
        self::assertResponseStatusCodeSame(201);
        $pending = $this->call('GET', '/reports/adjustments?customer=100&type=debt&state=pending');
        self::assertSame(1, $pending['total']);
        self::assertSame('25.00', $pending['amount']);
        $order = $this->call('POST', '/orders/import', ['orderNumber' => '456']);
        $preview = $this->call('POST', '/commissions/preview', ['orderIds' => [$order['id']], 'mode' => 'atg']);
        self::assertSame('220.00', $preview['gross']);
        self::assertSame('195.00', $preview['net']);
        $commission = $this->call('POST', '/commissions', ['orderIds' => [$order['id']], 'mode' => 'atg', 'ruleVersion' => 1, 'expectedAdjustmentIds' => $preview['adjustmentIds'], 'reason' => 'Autoagenciamento com sobrepreço e débito conferidos']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('atg', $commission['mode']);
        $listed = $this->call('GET', '/commissions');
        self::assertSame('atg', $listed['items'][0]['mode']);
        $detail = $this->call('GET', '/commissions/'.$commission['id']);
        self::assertSame('atg', $detail['mode']);
        self::assertSame('margin_table', $commission['calculation']['effectiveBasis']);
        self::assertSame('300.000000', $commission['calculation']['items'][0]['unitReference']);
        $this->login('finance');
        $this->call('POST', '/commissions/'.$commission['id'].'/approve');
        $date = date('Y-m-d', strtotime('-1 day'));
        $this->call('POST', '/commissions/'.$commission['id'].'/payment', ['amount' => '195.00', 'paidAt' => $date, 'notes' => 'Pagamento ATG conferido sem vínculo opcional']);
        self::assertResponseIsSuccessful();
        $filters = '?customer=100&status=paid&mode=atg&orderNumber=456&dateBasis=paid&from='.$date.'&to='.$date;
        $paid = $this->call('GET', '/reports/commissions'.$filters);
        self::assertSame(1, $paid['total']);
        self::assertSame('195.00', $paid['amount']);
        self::assertSame('atg', $paid['items'][0]['mode']);
        self::assertSame('456 · NF 900', $paid['items'][0]['orders']);
        $other = $this->call('GET', '/reports/commissions?customer=999&status=paid');
        self::assertSame(0, $other['total']);
        $normal = $this->call('GET', '/reports/commissions?mode=normal');
        self::assertSame(0, $normal['total']);
        $deducted = $this->call('GET', '/reports/adjustments?customer=100&type=debt&state=deducted');
        self::assertSame($debt['id'], $deducted['items'][0]['id']);
        self::assertSame($commission['code'], $deducted['items'][0]['commission_code']);
        self::assertSame('paid', $deducted['items'][0]['commission_status']);
        self::assertSame('25.00', $deducted['amount']);
        foreach (['commissions'.$filters, 'adjustments?customer=100&type=debt&state=deducted'] as $report) {
            [$kind, $query] = explode('?', $report, 2);
            foreach (['csv', 'xlsx', 'pdf'] as $format) {
                $this->browser->request('GET', '/api/reports/'.$kind.'.'.$format.'?'.$query);
                self::assertResponseIsSuccessful();
                $content = $this->browser->getInternalResponse()->getContent();

                if ('csv' === $format) {
                    self::assertStringContainsString($commission['code'], $content);
                    self::assertStringNotContainsString('999', $content);

                    if ('commissions' === $kind) {
                        self::assertStringContainsString('ATG (Autoagenciamento)', $content);
                    }
                } elseif ('pdf' === $format) {
                    self::assertStringStartsWith('%PDF-', $content);
                } else {
                    self::assertStringStartsWith('PK', $content);

                    if ('commissions' === $kind) {
                        $path = tempnam(sys_get_temp_dir(), 'gcom-atg-xlsx-');
                        file_put_contents($path, $content);
                        $zip = new ZipArchive();

                        try {
                            self::assertTrue($zip->open($path));
                            self::assertStringContainsString('ATG (Autoagenciamento)', (string) $zip->getFromName('xl/sharedStrings.xml').(string) $zip->getFromName('xl/worksheets/sheet1.xml'));
                            self::assertStringContainsString('FFFBEB', $zip->getFromName('xl/styles.xml'));
                        } finally {
                            $zip->close();
                            unlink($path);
                        }
                    }
                }
            }
        }
    }

    public function testReportsReturnRealTotalsAndCsv(): void
    {
        $this->login('operator');
        $this->createCommission();
        $summary = $this->call('GET', '/reports/summary');
        self::assertSame('100.00', $summary['totals']['outstanding']);
        $this->browser->request('GET', '/api/reports/commissions.csv');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=utf-8');
    }

    public function testFormulaConfigurationRequiresAdministratorAndCannotChangeHistory(): void
    {
        $this->login('operator');
        $first = $this->createCommission();
        self::assertSame('100.00', $first['grossAmount']);
        self::assertSame('80.0000', $first['calculation']['rule']['percentage']);
        self::assertSame('25.000000', $first['calculation']['deductedFreight']);
        $settings = ['expectedVersion' => 1, 'percentage' => '60', 'basis' => 'margin_psd', 'priceContexts' => [['branch' => '*', 'orderRegion' => 2, 'psdRegion' => 1, 'pscfRegion' => 2]], 'atgPercentage' => '80', 'returnPercentage' => '80', 'atgReturnPercentage' => '100', 'subtractFreight' => true, 'applyReferenceDiscount' => false, 'reason' => 'Alteração validada pelo administrador'];
        foreach (['operator', 'finance', 'auditor'] as $name) {
            $this->login($name);
            $this->call('POST', '/settings/commission-calculation', $settings);
            self::assertResponseStatusCodeSame(403);
        }
        $this->login('admin');
        $rule = $this->call('POST', '/settings/commission-calculation', $settings);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(2, $rule['version']);
        $this->call('POST', '/settings/commission-calculation', $settings);
        self::assertResponseStatusCodeSame(409);
        $this->login('operator');
        $order = $this->call('POST', '/orders/import', ['orderNumber' => '456']);
        $data = ['orderIds' => [$order['id']], 'ruleVersion' => 1, 'reason' => 'Nova comissão calculada automaticamente'];
        $this->call('POST', '/commissions', $data);
        self::assertResponseStatusCodeSame(409);
        $data['ruleVersion'] = 2;
        $this->call('POST', '/commissions', [...$data, 'grossAmount' => '1.00']);
        self::assertResponseStatusCodeSame(422);
        $this->call('POST', '/commissions', [...$data, 'percentage' => '100']);
        self::assertResponseStatusCodeSame(422);
        $preview = $this->call('POST', '/commissions/preview', ['orderIds' => $data['orderIds']]);
        self::assertSame('75.00', $preview['gross']);
        $second = $this->call('POST', '/commissions', $data);
        self::assertResponseStatusCodeSame(201);
        self::assertSame($preview['gross'], $second['grossAmount']);
        $unchanged = $this->call('GET', '/commissions/'.$first['id']);
        self::assertSame($first['calculation'], $unchanged['calculation']);
        self::assertSame('100.00', $unchanged['grossAmount']);
        $db = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        foreach (['UPDATE commission_rule SET reason = \'changed\' WHERE id = 1', 'UPDATE commission SET gross_amount = 90 WHERE id = 1'] as $sql) {
            try {
                $db->executeStatement($sql);
                self::fail('Calculation history must be immutable');
            } catch (\Doctrine\DBAL\Exception $e) {
                self::assertStringContainsString('immutable', $e->getMessage());
            }
        }
    }

    public function testPdfXlsxAndCsvUseTheSameCommissionData(): void
    {
        $this->login('operator');
        $commission = $this->createCommission();
        self::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement('UPDATE commission SET customer_name = :name WHERE id = :id', ['name' => '=SUM(1,2)', 'id' => $commission['id']]);
        foreach (['pdf' => 'application/pdf', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'csv' => 'text/csv; charset=utf-8'] as $format => $type) {
            $this->browser->request('GET', '/api/reports/commissions.'.$format);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('Content-Type', $type);
            $response = $this->browser->getResponse();
            self::assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
            $content = $this->browser->getInternalResponse()->getContent();
            $file = tempnam(sys_get_temp_dir(), 'gcom-export-test-');
            file_put_contents($file, $content);

            if ('pdf' === $format) {
                self::assertStringStartsWith('%PDF-', $content);
                self::assertGreaterThan(10000, strlen($content));
            } elseif ('xlsx' === $format) {
                $zip = new ZipArchive();
                self::assertTrue($zip->open($file));
                $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
                self::assertStringContainsString($commission['code'], $xml);
                self::assertStringContainsString('<v>100</v>', $xml);
                self::assertStringNotContainsString('<f>', $xml);
                self::assertStringContainsString('=SUM(1,2)', $xml);
                $zip->close();
            } else {
                self::assertStringContainsString($commission['code'], $content);
                self::assertStringContainsString('100.00', $content);
                self::assertStringContainsString("'=SUM(1,2)", $content);
            }
            unlink($file);
        }
    }

    public function testPostgresEventsAndHttpLogsShareCorrelationId(): void
    {
        $this->browser->disableReboot();
        $events = [];
        $container = self::getContainer();
        $handlers = [];
        foreach (['requests', 'responses', 'database_queries'] as $channel) {
            $handler = new \Monolog\Handler\TestHandler();
            $container->get('monolog.logger.'.$channel)->pushHandler($handler);
            $handlers[] = $handler;
        }
        $requests = $container->get(\Symfony\Component\HttpFoundation\RequestStack::class);
        $container->get('event_dispatcher')->addListener(\App\Database\Event\DatabaseQueryEvent::class, static function (\App\Database\Event\DatabaseQueryEvent $event) use (&$events, $requests): void {
            $events[] = ['sql' => $event->sql, 'connection' => $event->connection, 'requestId' => $requests->getMainRequest()?->attributes->get('request_id')];
        });
        $this->login('operator');
        $id = $this->browser->getResponse()->headers->get('X-Request-Id');
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id);
        $correlated = array_filter($events, static fn ($event) => $event['requestId'] === $id && 'postgresql' === $event['connection']);
        self::assertNotEmpty($correlated);
        $matched = [];
        foreach ($handlers as $handler) {
            foreach ($handler->getRecords() as $record) {
                if (($record->context['request_id'] ?? null) === $id) {
                    $matched[] = $record->message;
                    $line = json_encode($record->toArray(), \JSON_THROW_ON_ERROR);
                    self::assertStringNotContainsString('test-password-123', $line);
                    self::assertStringNotContainsString('operator@example.test', $line);
                }
            }
        }
        self::assertContains('http.request', $matched);
        self::assertContains('http.response', $matched);
        self::assertContains('database.query', $matched);
    }

    public function testPreviewIncludesEveryDeductionBeyondTheListPage(): void
    {
        $this->login('operator');
        $order = $this->call('POST', '/orders/import', ['orderNumber' => '123']);

        for ($index = 0; $index < 31; ++$index) {
            $this->call('POST', '/adjustments', ['customerCode' => '100', 'type' => 'debt', 'amount' => '1.00', 'reason' => 'Débito conferido para simulação', 'sourceReference' => 'Documento '.$index]);
            self::assertResponseStatusCodeSame(201);
        }

        $list = $this->call('GET', '/adjustments?customer=100&available=1');
        self::assertCount(30, $list['items']);
        $preview = $this->call('POST', '/commissions/preview', ['orderIds' => [$order['id']]]);
        self::assertResponseIsSuccessful();
        self::assertCount(31, $preview['adjustments']);
        self::assertCount(31, $preview['adjustmentIds']);
        self::assertSame('31.00', $preview['deductions']);
        self::assertSame('69.00', $preview['net']);
        self::assertNotEmpty($preview['calculation']['items'][0]['description']);
    }

    public function testInvalidNestedRuleInputsDoNotCreateVersions(): void
    {
        $this->login('admin');
        $settings = ['expectedVersion' => 1, 'percentage' => '80', 'basis' => 'margin_psd', 'priceContexts' => [['branch' => '*', 'orderRegion' => 2, 'psdRegion' => 1, 'pscfRegion' => 2]], 'atgPercentage' => '80', 'returnPercentage' => '80', 'atgReturnPercentage' => '100', 'subtractFreight' => true, 'applyReferenceDiscount' => false, 'reason' => 'Alteração conferida pelo administrador'];

        foreach ([['bad'], [['branch' => '*']], [['branch' => '*', 'orderRegion' => '2', 'psdRegion' => 1, 'pscfRegion' => 2]], [['branch' => '*', 'orderRegion' => 2, 'psdRegion' => 1, 'pscfRegion' => 1]], [$settings['priceContexts'][0], $settings['priceContexts'][0]]] as $contexts) {
            $this->call('POST', '/settings/commission-calculation', [...$settings, 'priceContexts' => $contexts]);
            self::assertResponseStatusCodeSame(422);
        }

        $current = $this->call('GET', '/settings/commission-calculation');
        self::assertSame(1, $current['version']);
    }

    public function testCommissionAndPaymentAcceptOmittedObservations(): void
    {
        $this->login('operator');
        $order = $this->call('POST', '/orders/import', ['orderNumber' => '123']);
        $commission = $this->call('POST', '/commissions', ['orderIds' => [$order['id']], 'ruleVersion' => 1]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('', $commission['calculation']['reason']);

        $this->login('finance');
        $this->call('POST', '/commissions/'.$commission['id'].'/approve');
        self::assertResponseIsSuccessful();
        $this->call('POST', '/commissions/'.$commission['id'].'/payment', ['amount' => $commission['netAmount'], 'paidAt' => date('Y-m-d')]);
        self::assertResponseIsSuccessful();
        $detail = $this->call('GET', '/commissions/'.$commission['id']);
        self::assertSame('', $detail['payment']['notes']);
        self::assertNull($detail['payment']['manualReason']);
    }

    public function testManualPaymentAcceptsNullObservations(): void
    {
        $this->login('operator');
        $order = $this->call('POST', '/orders/import', ['orderNumber' => '123']);
        $commission = $this->call('POST', '/commissions', ['orderIds' => [$order['id']], 'ruleVersion' => 1, 'reason' => null]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('', $commission['calculation']['reason']);

        $this->login('finance');
        $this->call('POST', '/commissions/'.$commission['id'].'/approve');
        $this->call('POST', '/commissions/'.$commission['id'].'/payment', ['amount' => '90.00', 'paidAt' => date('Y-m-d'), 'manualAmount' => true, 'manualReason' => null, 'notes' => null]);
        self::assertResponseIsSuccessful();
        $detail = $this->call('GET', '/commissions/'.$commission['id']);
        self::assertSame('90.00', $detail['payment']['amount']);
        self::assertSame('100.00', $detail['payment']['calculatedAmount']);
        self::assertTrue($detail['payment']['manualAmount']);
        self::assertNull($detail['payment']['manualReason']);
        self::assertSame('', $detail['payment']['notes']);
    }
}
