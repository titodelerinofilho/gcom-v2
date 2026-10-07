<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Adjustment;
use App\Entity\AuditEvent;
use App\Entity\Commission;
use App\Entity\OrderSnapshot;
use App\Entity\PaymentLink;
use App\Entity\User;

final class ApiView
{
    public static function user(User $u): array
    {
        return ['id' => $u->getId(), 'name' => $u->getName(), 'email' => $u->getEmail(), 'roles' => $u->getRoles(), 'active' => $u->getActive()];
    }

    public static function order(OrderSnapshot $o, bool $detail = false): array
    {
        $result = ['id' => $o->getId(), 'orderNumber' => $o->getOrderNumber(), 'customerCode' => $o->getCustomerCode(), 'customerName' => $o->getCustomerName(), 'total' => $o->getTotal(), 'capturedAt' => $o->getCapturedAt()->format(\DATE_ATOM), 'commissionId' => $o->getCommission()?->getId(), 'itemCount' => $o->getItems()->count()];

        if ($detail) {
            $result['header'] = $o->getHeader();
            $result['items'] = array_map(static fn ($i) => ['id' => $i->getId(), 'productCode' => $i->getProductCode(), 'description' => $i->getDescription(), 'quantity' => $i->getQuantity(), 'unitPrice' => $i->getUnitPrice(), 'referencePrice' => $i->getReferencePrice(), 'raw' => $i->getRaw()], $o->getItems()->toArray());
        }

        return $result;
    }

    public static function commission(Commission $c, bool $detail = false): array
    {
        $result = ['id' => $c->getId(), 'code' => $c->getCode(), 'customerCode' => $c->getCustomerCode(), 'customerName' => $c->getCustomerName(), 'grossAmount' => $c->getGrossAmount(), 'deductions' => $c->getDeductions(), 'netAmount' => $c->getNetAmount(), 'status' => $c->getStatus(), 'createdAt' => $c->getCreatedAt()->format(\DATE_ATOM), 'createdBy' => ['id' => $c->getCreatedBy()->getId(), 'name' => $c->getCreatedBy()->getName()], 'approvedBy' => $c->getApprovedBy()?->getName(), 'approvedAt' => $c->getApprovedAt()?->format(\DATE_ATOM)];

        if ($detail) {
            $result['calculation'] = $c->getCalculation();
            $result['orders'] = array_map(static fn ($o) => self::order($o, true), $c->getOrders()->toArray());
        }

        return $result;
    }

    public static function adjustment(Adjustment $a): array
    {
        return ['id' => $a->getId(), 'customerCode' => $a->getCustomerCode(), 'type' => $a->getType(), 'amount' => $a->getAmount(), 'reason' => $a->getReason(), 'sourceReference' => $a->getSourceReference(), 'sourceSnapshot' => $a->getSourceSnapshot(), 'commissionId' => $a->getCommission()?->getId(), 'createdAt' => $a->getCreatedAt()->format(\DATE_ATOM)];
    }

    public static function payment(PaymentLink $p): \App\Dto\Commission\Output\PaymentOutput
    {
        return new \App\Dto\Commission\Output\PaymentOutput($p);
    }

    public static function audit(AuditEvent $e): array
    {
        return ['id' => $e->getId(), 'actor' => $e->getActor()?->getName() ?? 'Sistema', 'action' => $e->getAction(), 'subject' => $e->getSubject(), 'details' => $e->getDetails(), 'requestId' => $e->getRequestId(), 'ip' => $e->getIp(), 'createdAt' => $e->getCreatedAt()->format(\DATE_ATOM)];
    }
}
