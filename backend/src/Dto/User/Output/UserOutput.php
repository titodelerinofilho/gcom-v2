<?php

declare(strict_types=1);

namespace App\Dto\User\Output;

use App\Entity\User\User;

final readonly class UserOutput
{
    public ?int $id;

    public string $name;

    public string $email;

    /** @var list<string> */
    public array $roles;

    public bool $active;

    public function __construct(User $user)
    {
        $this->id = $user->getId();
        $this->name = $user->getName();
        $this->email = $user->getEmail();
        $this->roles = $user->getRoles();
        $this->active = $user->getActive();
    }
}
