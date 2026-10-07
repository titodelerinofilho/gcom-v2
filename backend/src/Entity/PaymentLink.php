<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\PaymentLinkRepository::class)]
#[ORM\Table(name: 'payment_link')]
#[ORM\UniqueConstraint(name: 'payment_routine_reference_unique', columns: ['routine', 'reference'])]
class PaymentLink
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\OneToOne, ORM\JoinColumn(nullable: false, unique: true)]
    private Commission $commission;

    public function getCommission(): Commission
    {
        return $this->commission;
    }

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $routine;

    public function getRoutine(): ?string
    {
        return $this->routine;
    }

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference;

    public function getReference(): ?string
    {
        return $this->reference;
    }

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $amount;

    public function getAmount(): string
    {
        return $this->amount;
    }

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $calculatedAmount;

    public function getCalculatedAmount(): string
    {
        return $this->calculatedAmount;
    }

    #[ORM\Column]
    private bool $manualAmount;

    public function isManualAmount(): bool
    {
        return $this->manualAmount;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $manualReason;

    public function getManualReason(): ?string
    {
        return $this->manualReason;
    }

    #[ORM\Column]
    private DateTimeImmutable $paidAt;

    public function getPaidAt(): DateTimeImmutable
    {
        return $this->paidAt;
    }

    #[ORM\Column]
    private DateTimeImmutable $confirmedAt;

    public function getConfirmedAt(): DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    private User $confirmedBy;

    public function getConfirmedBy(): User
    {
        return $this->confirmedBy;
    }

    #[ORM\Column(length: 30)]
    private string $verification = 'none';

    public function getVerification(): string
    {
        return $this->verification;
    }

    #[ORM\Column(type: 'text')]
    private string $notes;

    public function getNotes(): string
    {
        return $this->notes;
    }

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $winthorDetails = null;

    public function getWinthorDetails(): ?array
    {
        return $this->winthorDetails;
    }

    public function linkWinthor(string $recnum, ?array $details): void
    {
        if (null !== $this->reference) {
            throw new \App\Exception\BusinessException('Pagamento já possui RECNUM vinculado.', 409);
        }
        $this->routine = '749';
        $this->reference = $recnum;
        $this->winthorDetails = $details;
        $this->verification = null === $details ? 'manual_reference' : 'winthor_lookup';
    }

    public function __construct(Commission $commission, ?string $routine, ?string $reference, string $amount, DateTimeImmutable $paidAt, User $actor, string $notes, bool $manualAmount = false, ?string $manualReason = null)
    {
        $this->commission = $commission;
        $this->routine = $routine;
        $this->reference = $reference;
        $this->amount = $amount;
        $this->calculatedAmount = $commission->getNetAmount();
        $this->manualAmount = $manualAmount;
        $this->manualReason = $manualReason;
        $this->paidAt = $paidAt;
        $this->confirmedBy = $actor;
        $this->notes = $notes;
        $this->confirmedAt = new DateTimeImmutable();
    }
}
