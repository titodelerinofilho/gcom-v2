<?php

declare(strict_types=1);

namespace App\Dto\Enterprise\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateEnterpriseInput
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 180)]
        public string $legalName,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 120)]
        public string $tradeName,
        #[Assert\Regex(pattern: '/^(?:[0-9]{14}|[0-9]{2}\.[0-9]{3}\.[0-9]{3}\/[0-9]{4}-[0-9]{2})?$/D')]
        public string $cnpj = '',
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email = '',
        #[Assert\Length(max: 30)]
        public string $phone = '',
        #[Assert\Length(max: 500)]
        public string $address = '',
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[A-Z0-9][A-Z0-9_-]{0,19}$/D')]
        public string $commissionPrefix = 'GCOM',
    ) {
    }
}
