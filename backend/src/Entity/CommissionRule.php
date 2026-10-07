<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'commission_rule')]
class CommissionRule
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'json')]
    private array $settings;

    #[ORM\Column(length: 2000)]
    private string $reason;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true)]
    private ?User $createdBy;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(array $settings, string $reason, ?User $createdBy)
    {
        $this->settings = $settings;
        $this->reason = $reason;
        $this->createdBy = $createdBy;
        $this->createdAt = new DateTimeImmutable();
    }

    public function view(): array
    {
        return ['version' => $this->id, ...$this->settings, 'reason' => $this->reason, 'createdAt' => $this->createdAt->format(\DATE_ATOM), 'createdBy' => $this->createdBy?->getName()];
    }
}
