<?php

declare(strict_types=1);

namespace App\Integration\Winthor;

use App\Exception\Business\BusinessException;
use App\Repository\Winthor\PclancRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PaymentGateway
{
    public function __construct(
        private PclancRepository $payments,
        private PaymentSnapshotFactory $snapshots,
        #[Autowire('%env(bool:WINTHOR_749_LOOKUP_ENABLED)%')]
        private bool $lookupEnabled,
    ) {
    }

    public function fetch(string $recnum): ?array
    {
        if (false === preg_match('/^[1-9][0-9]{0,17}$/D', $recnum)) {
            throw new BusinessException('RECNUM inválido.');
        }

        if (false === $this->lookupEnabled) {
            return null;
        }

        return $this->snapshots->create($recnum, $this->payments->findByRecnum($recnum));
    }
}
