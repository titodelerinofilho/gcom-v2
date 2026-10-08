<?php

declare(strict_types=1);

namespace App\Dto\Commission\Output;

use App\Entity\User\User;

final readonly class CommissionActorOutput
{
    public ?int $id;

    public string $name;

    public function __construct(User $actor)
    {
        $this->id = $actor->getId();
        $this->name = $actor->getName();
    }
}
