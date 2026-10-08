<?php

declare(strict_types=1);

namespace App\Dto\Audit\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListAuditInput
{
    public ?string $action;

    public ?string $subject;

    #[Assert\Positive]
    public int $page;

    public function __construct(
        ?string $action = null,
        ?string $subject = null,
        int $page = 1,
    ) {
        $this->action = null === $action || '' === trim($action) ? null : trim($action);
        $this->subject = null === $subject || '' === trim($subject) ? null : trim($subject);
        $this->page = min(100000, max(1, $page));
    }
}
