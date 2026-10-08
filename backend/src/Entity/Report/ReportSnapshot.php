<?php

declare(strict_types=1);

namespace App\Entity\Report;

use App\Entity\User\User;
use App\Repository\Report\ReportSnapshotRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportSnapshotRepository::class)]
#[ORM\Table(name: 'report_snapshot')]
class ReportSnapshot
{
    #[ORM\Id, ORM\Column(length: 32)]
    private string $id;

    #[ORM\Column(length: 20)]
    private string $kind;

    #[ORM\Column(length: 10)]
    private string $dateFrom;

    #[ORM\Column(length: 10)]
    private string $dateToExclusive;

    #[ORM\Column(type: 'json')]
    private array $filters;

    #[ORM\Column(type: 'json')]
    private array $rows;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    public function __construct(string $kind, string $from, string $to, array $filters, array $rows, User $actor)
    {
        $this->id = bin2hex(random_bytes(16));
        $this->kind = $kind;
        $this->dateFrom = $from;
        $this->dateToExclusive = $to;
        $this->filters = $filters;
        $this->rows = $rows;
        $this->createdAt = new DateTimeImmutable();
        $this->createdBy = $actor;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getDateFrom(): string
    {
        return $this->dateFrom;
    }

    public function getDateToExclusive(): string
    {
        return $this->dateToExclusive;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }
}
