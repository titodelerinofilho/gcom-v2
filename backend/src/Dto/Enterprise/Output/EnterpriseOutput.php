<?php

declare(strict_types=1);

namespace App\Dto\Enterprise\Output;

use App\Entity\Enterprise\Enterprise;

final readonly class EnterpriseOutput
{
    public string $legalName;

    public string $tradeName;

    public string $cnpj;

    public string $email;

    public string $phone;

    public string $address;

    public string $commissionPrefix;

    public bool $configured;

    public function __construct(?Enterprise $enterprise)
    {
        $this->configured = null !== $enterprise;
        $enterprise ??= new Enterprise();

        $this->legalName = $enterprise->getLegalName();
        $this->tradeName = $enterprise->getTradeName();
        $this->cnpj = $enterprise->getCnpj();
        $this->email = $enterprise->getEmail();
        $this->phone = $enterprise->getPhone();
        $this->address = $enterprise->getAddress();
        $this->commissionPrefix = $enterprise->getCommissionPrefix();
    }
}
