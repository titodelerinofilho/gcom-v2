<?php

declare(strict_types=1);

namespace App\Dto\User\Input;

use Symfony\Component\Validator\Constraints as Assert;

readonly class UserWriteInput
{
    public const ROLES = ['ROLE_ADMIN', 'ROLE_OPERATOR', 'ROLE_FINANCE', 'ROLE_AUDITOR'];

    #[Assert\Length(min: 2, max: 120)]
    public string $name;

    #[Assert\Email(message: 'Email inválido.')]
    #[Assert\Length(max: 180)]
    #[Assert\NotBlank]
    public string $email;

    #[Assert\Count(min: 1, max: 4)]
    #[Assert\All([new Assert\Type('string'), new Assert\Choice(choices: self::ROLES)])]
    public array $roles;

    public bool $active;

    #[Assert\Length(min: 12, max: 128, minMessage: 'A senha deve conter de 12 a 128 caracteres.', maxMessage: 'A senha deve conter de 12 a 128 caracteres.')]
    #[Assert\NotBlank(groups: ['create'], message: 'A senha deve conter de 12 a 128 caracteres.')]
    public ?string $password;

    public function __construct(
        string $name,
        string $email,
        array $roles,
        bool $active = true,
        ?string $password = null,
    ) {
        $this->name = trim($name);
        $this->email = mb_strtolower(trim($email));
        $this->roles = $roles;
        $this->active = $active;
        $this->password = '' === $password ? null : $password;
    }
}
