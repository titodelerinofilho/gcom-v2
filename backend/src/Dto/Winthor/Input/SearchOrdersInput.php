<?php

declare(strict_types=1);

namespace App\Dto\Winthor\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchOrdersInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Informe o cliente principal.')]
        #[Assert\Regex(pattern: '/^[1-9][0-9]{0,17}$/D', message: 'Código do cliente principal inválido.')]
        public string $customer,
        #[Assert\NotBlank(message: 'Informe a data inicial.')]
        #[Assert\Date(message: 'Data inicial inválida.')]
        public string $from,
        #[Assert\NotBlank(message: 'Informe a data final.')]
        #[Assert\Date(message: 'Data final inválida.')]
        public string $to,
    ) {
    }
}
