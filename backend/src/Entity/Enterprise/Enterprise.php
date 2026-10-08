<?php

declare(strict_types=1);

namespace App\Entity\Enterprise;

use App\Dto\Enterprise\Input\UpdateEnterpriseInput;
use App\Repository\Enterprise\EnterpriseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnterpriseRepository::class)]
#[ORM\Table(name: 'enterprise')]
class Enterprise
{
    #[ORM\Id, ORM\Column]
    private int $id = 1;

    #[ORM\Column(length: 180)]
    private string $legalName = 'GCOM';

    #[ORM\Column(length: 120)]
    private string $tradeName = 'GCOM';

    #[ORM\Column(length: 18)]
    private string $cnpj = '';

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column(length: 30)]
    private string $phone = '';

    #[ORM\Column(length: 500)]
    private string $address = '';

    #[ORM\Column(length: 20)]
    private string $commissionPrefix = 'GCOM';

    public function update(UpdateEnterpriseInput $input): void
    {
        $this->legalName = $input->legalName;
        $this->tradeName = $input->tradeName;
        $this->cnpj = $input->cnpj;
        $this->email = $input->email;
        $this->phone = $input->phone;
        $this->address = $input->address;
        $this->commissionPrefix = $input->commissionPrefix;
    }

    public function getLegalName(): string
    {
        return $this->legalName;
    }

    public function getTradeName(): string
    {
        return $this->tradeName;
    }

    public function getCnpj(): string
    {
        return $this->cnpj;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getCommissionPrefix(): string
    {
        return $this->commissionPrefix;
    }
}
