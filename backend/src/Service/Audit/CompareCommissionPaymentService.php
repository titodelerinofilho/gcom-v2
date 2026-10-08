<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Dto\Audit\Output\PaymentAuditDifferenceOutput;
use App\Dto\Audit\Output\PaymentAuditOutput;
use App\Entity\Commission\PaymentLink;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class CompareCommissionPaymentService
{
    public function compare(PaymentLink $payment, ?array $details, string $source, ?string $notice): ?PaymentAuditOutput
    {
        if (null === $payment->getReference()) {
            return null;
        }

        $expected = $payment->getCommission()->getNetAmount();
        $confirmed = $payment->getAmount();
        $record = $details['records'][0] ?? [];
        $record = array_change_key_case($record, \CASE_UPPER);
        $launch = $this->amount($record['VALOR'] ?? null);
        $paid = $this->amount($record['VPAGO'] ?? null);

        if (null === ($record['DTPAGTO'] ?? null) && (null === $paid || true === BigDecimal::of($paid)->isZero())) {
            $paid = null;
        }

        $differences = [];
        foreach (['Pagamento confirmado no GCOM' => $confirmed, 'Valor lançado na rotina 749' => $launch, 'Valor pago no Winthor' => $paid] as $label => $amount) {
            if (null === $amount || true === BigDecimal::of($expected)->isEqualTo($amount)) {
                continue;
            }

            $differences[] = new PaymentAuditDifferenceOutput($label, $expected, $amount, (string) BigDecimal::of($amount)->minus($expected)->toScale(2, RoundingMode::HalfUp));
        }

        return new PaymentAuditOutput($payment->getReference(), $expected, $confirmed, $launch, $paid, $source, $notice, $differences);
    }

    private function amount(mixed $value): ?string
    {
        if (null === $value || '' === trim((string) $value)) {
            return null;
        }

        $value = str_replace(',', '.', trim((string) $value));

        if (1 !== preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) {
            return null;
        }

        return (string) BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp);
    }
}
