<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Dto\Commission\Output\PaidReturnCommissionOutput;
use App\Dto\Commission\Output\ReturnItemOutput;
use App\Repository\Commission\PaymentLinkRepository;

final readonly class CorrelateReturnPaymentsService
{
    public function __construct(private PaymentLinkRepository $payments)
    {
    }

    /** @return array<string, list<ReturnItemOutput>> */
    public function correlate(string $customer, array $groupedReturns): array
    {
        $orderNumbers = [];
        foreach ($groupedReturns as $lines) {
            foreach ($lines as $line) {
                $number = (string) ($line['NUMPED'] ?? '0');

                if ('0' !== $number && '' !== $number) {
                    $orderNumbers[] = $number;
                }
            }
        }

        $paidItems = [];
        foreach ($this->payments->findPaidForOrders($customer, array_values(array_unique($orderNumbers))) as $payment) {
            $commission = $payment->getCommission();
            $output = new PaidReturnCommissionOutput($commission->getId(), $commission->getCode(), $commission->getCalculation()['mode'] ?? 'normal', $payment->getPaidAt()->format('Y-m-d'));
            foreach ($commission->getOrders() as $order) {
                $header = $order->getHeader();
                foreach ($order->getItems() as $item) {
                    $paidItems[$order->getOrderNumber()][$item->getProductCode()][] = ['payment' => $output, 'customer' => (string) ($header['CODCLI'] ?? ''), 'branch' => (string) ($header['CODFILIAL'] ?? '')];
                }
            }
        }

        $result = [];
        foreach ($groupedReturns as $transaction => $lines) {
            $result[$transaction] = [];
            foreach ($lines as $line) {
                $number = (string) ($line['NUMPED'] ?? '0');
                $product = (string) $line['CODPROD'];
                $finalCustomer = (string) ($line['FINAL_CUSTOMER'] ?? $line['CODCLI'] ?? '');
                $branch = (string) ($line['CODFILIAL'] ?? '');
                $matches = [];
                foreach ($paidItems[$number][$product] ?? [] as $paid) {
                    if ('' === $finalCustomer || $finalCustomer !== $paid['customer'] || '' === $branch || $branch !== $paid['branch']) {
                        continue;
                    }

                    $matches[$paid['payment']->commissionId] = $paid['payment'];
                }

                $status = '0' === $number || '' === $number || '' === $finalCustomer || '' === $branch ? 'unmatched' : ([] === $matches ? 'not_found' : 'paid');
                $result[$transaction][] = new ReturnItemOutput($number, $product, (string) ($line['DESCRICAO'] ?? ''), (string) $line['QT'], $status, array_values($matches));
            }
        }

        return $result;
    }
}
