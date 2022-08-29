<?php

declare(strict_types=1);

namespace App\Model;

use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;

#[ORM\Entity]
class Permissions
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(length: 50)]
    private string $userIdentifier;

    #[ORM\Column()]
    private bool $allowShowComissions = false;

    #[ORM\Column()]
    private bool $allowPostComissions = false;

    #[ORM\Column()]
    private bool $allowShowReturns = false;

    #[ORM\Column()]
    private bool $allowPostReturns = false;

    #[ORM\Column()]
    private bool $allowPostSquares = false;

    #[ORM\Column()]
    private bool $allowModifyParameters;

    #[ORM\Column()]
    private DateTimeImmutable $createdAt;

    #[ORM\Column()]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column()]
    private DateTimeImmutable $deletedAt;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }

    public function isAllowShowComissions(): bool
    {
        return $this->allowShowComissions;
    }

    public function setAllowShowComissions(bool $allowShowComissions): void
    {
        $this->allowShowComissions = $allowShowComissions;
    }

    public function isAllowPostComissions(): bool
    {
        return $this->allowPostComissions;
    }

    public function setAllowPostComissions(bool $allowPostComissions): void
    {
        $this->allowPostComissions = $allowPostComissions;
    }

    public function isAllowShowReturns(): bool
    {
        return $this->allowShowReturns;
    }

    public function setAllowShowReturns(bool $allowShowReturns): void
    {
        $this->allowShowReturns = $allowShowReturns;
    }

    public function isAllowPostReturns(): bool
    {
        return $this->allowPostReturns;
    }

    public function setAllowPostReturns(bool $allowPostReturns): void
    {
        $this->allowPostReturns = $allowPostReturns;
    }

    public function isAllowPostSquares(): bool
    {
        return $this->allowPostSquares;
    }

    public function setAllowPostSquares(bool $allowPostSquares): void
    {
        $this->allowPostSquares = $allowPostSquares;
    }

    public function isAllowModifyParameters(): bool
    {
        return $this->allowModifyParameters;
    }

    public function setAllowModifyParameters(bool $allowModifyParameters): void
    {
        $this->allowModifyParameters = $allowModifyParameters;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getDeletedAt(): DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }
}