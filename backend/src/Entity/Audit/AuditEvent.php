<?php

declare(strict_types=1);

namespace App\Entity\Audit;

use App\Entity\User\User;
use App\Repository\Audit\AuditEventRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditEventRepository::class)]
#[ORM\Table(name: 'audit_event')]
#[ORM\Index(name: 'audit_created_idx', columns: ['created_at'])]
#[ORM\Index(name: 'audit_subject_idx', columns: ['subject'])]
class AuditEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\ManyToOne]
    private ?User $actor = null;

    public function getActor(): ?User
    {
        return $this->actor;
    }

    #[ORM\Column(length: 80)]
    private string $action;

    public function getAction(): string
    {
        return $this->action;
    }

    #[ORM\Column(length: 120)]
    private string $subject;

    public function getSubject(): string
    {
        return $this->subject;
    }

    #[ORM\Column(type: 'json')]
    private array $details;

    public function getDetails(): array
    {
        return $this->details;
    }

    #[ORM\Column(length: 64)]
    private string $requestId;

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    #[ORM\Column(length: 64)]
    private string $ip;

    public function getIp(): string
    {
        return $this->ip;
    }

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __construct(?User $actor, string $action, string $subject, array $details, string $requestId, string $ip)
    {
        $this->actor = $actor;
        $this->action = $action;
        $this->subject = $subject;
        $this->details = $details;
        $this->requestId = $requestId;
        $this->ip = $ip;
        $this->createdAt = new DateTimeImmutable();
    }
}
