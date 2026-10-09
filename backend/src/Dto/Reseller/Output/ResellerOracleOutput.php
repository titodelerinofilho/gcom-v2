<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class ResellerOracleOutput
{
    /**
     * @param list<SalesMonthOutput>  $monthly
     * @param list<TopCustomerOutput> $topCustomers
     */
    public function __construct(
        public CustomerOutput $customer,
        public ActivityOutput $activity,
        public DebtSummaryOutput $debtSummary,
        public DebtsOutput $debts,
        public CancellationsOutput $cancellations,
        public array $monthly,
        public array $topCustomers,
    ) {
    }
}
