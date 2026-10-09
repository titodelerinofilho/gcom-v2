export type ProfilePage<T> = { items: T[]; total: number; page: number; pageSize: number };
export type PaidCommission = {
  id: number;
  code: string;
  paidAt: string;
  grossAmount: string;
  deductions: string;
  netAmount: string;
  paidAmount: string;
  manualAmount: boolean;
};
export type AppliedReturn = {
  id: number;
  reference: string;
  amount: string;
  appliedAt: string;
  commissionId: number;
  commissionCode: string;
  commissionStatus: string;
  reason: string;
};
export type ResellerDebt = {
  customerCode: string;
  customerName: string;
  own: boolean;
  transaction: string;
  installment: string;
  invoice: string | null;
  dueDate: string | null;
  daysLate: number;
  amount: string;
};
export type ResellerCancellation = {
  orderNumber: string;
  customerCode: string;
  customerName: string;
  cancelledAt: string;
  amount: string;
  reason: string;
  source: "order" | "invoice";
};
export type ProfileMonth = {
  month: string;
  orders: number;
  salesAmount: string;
  generatedAmount: string;
  paidAmount: string;
};
export type ResellerProfile = {
  customer: { code: string; name: string };
  from: string;
  to: string;
  generatedAt: string;
  activity: {
    soldOrders: number;
    salesAmount: string;
    linkedCustomers: number;
    activeCustomers: number;
    cancelledOrders: number;
    cancelledAmount: string;
    returnedAmount: string;
  };
  finance: {
    generatedCount: number;
    grossAmount: string;
    deductions: string;
    netAmount: string;
    pendingAmount: string;
    paidCount: number;
    paidAmount: string;
    appliedReturnsAmount: string;
  };
  debtSummary: {
    openTitles: number;
    openAmount: string;
    overdueTitles: number;
    overdueAmount: string;
    ownOverdueAmount: string;
    linkedOverdueAmount: string;
    maxLateDays: number;
  };
  rating: {
    tier: "gold" | "silver" | "bronze" | "unrated";
    label: string;
    score: number;
    confidence: string;
    cancellationRate: string | null;
    returnRate: string | null;
    overdueRate: string | null;
    version: string;
    components: { label: string; points: number; maximum: number; detail: string }[];
  };
  monthly: ProfileMonth[];
  topCustomers: { code: string; name: string; orders: number; salesAmount: string }[];
  paidCommissions: ProfilePage<PaidCommission>;
  appliedReturns: ProfilePage<AppliedReturn>;
  debts: ProfilePage<ResellerDebt>;
  cancellations: ProfilePage<ResellerCancellation>;
  notes: string[];
};
