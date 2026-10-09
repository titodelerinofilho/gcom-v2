<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class ResellerProfileOutput
{
    /**
     * @param list<ProfileMonthOutput> $monthly
     * @param list<TopCustomerOutput>  $topCustomers
     * @param list<string>             $notes
     */
    public function __construct(
        public CustomerOutput $customer,
        public string $from,
        public string $to,
        public string $generatedAt,
        public ActivityOutput $activity,
        public FinanceSummaryOutput $finance,
        public DebtSummaryOutput $debtSummary,
        public RatingOutput $rating,
        public array $monthly,
        public array $topCustomers,
        public PaidCommissionsOutput $paidCommissions,
        public AppliedReturnsOutput $appliedReturns,
        public DebtsOutput $debts,
        public CancellationsOutput $cancellations,
        public array $notes,
    ) {
    }
}
