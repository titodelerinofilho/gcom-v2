export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public requestId?: string,
  ) {
    super(message);
  }
}
export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers);
  if (options.body) headers.set("Content-Type", "application/json");
  if (options.method && !["GET", "HEAD"].includes(options.method)) {
    const csrfResponse = await fetch("/api/csrf", {
      credentials: "same-origin",
      cache: "no-store",
    });
    if (!csrfResponse.ok)
      throw new ApiError("Não foi possível validar a sessão.", csrfResponse.status);
    const csrf: { csrfToken: string } = await csrfResponse.json();
    headers.set("X-CSRF-Token", csrf.csrfToken);
    if (path === "/logout") {
      options.body = new URLSearchParams({ _csrf_token: csrf.csrfToken });
      headers.set("Content-Type", "application/x-www-form-urlencoded");
    }
  }
  const response = await fetch(`/api${path}`, {
    ...options,
    headers,
    credentials: "same-origin",
    cache: "no-store",
  });
  const data = await response.json().catch(() => ({ error: "Resposta inválida do servidor." }));
  if (!response.ok) {
    if (response.status === 401 && path !== "/login" && path !== "/me")
      window.location.assign("/login");
    throw new ApiError(
      data.error ?? "Falha ao executar a operação.",
      response.status,
      data.requestId,
    );
  }
  return data as T;
}
export const money = (value: string | number) =>
  new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(Number(value));
export const date = (value: string) =>
  new Intl.DateTimeFormat("pt-BR", { dateStyle: "short" }).format(
    new Date(value.length === 10 ? `${value}T12:00:00` : value),
  );
export type User = { id: number; name: string; email: string; roles: string[]; active: boolean };
export const dateTime = (value: string) =>
  new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "medium",
    timeZone: "America/Fortaleza",
  }).format(new Date(value));
export type Page<T> = { items: T[]; total: number; page: number };
export type Item = {
  id: number;
  productCode: string;
  description: string;
  quantity: string;
  unitPrice: string;
  referencePrice: string;
  raw: Record<string, unknown>;
};
export type Order = {
  invoiceNumber?: string | null;
  authorCustomerCode?: string | null;
  authorCustomerName?: string | null;
  id: number;
  orderNumber: string;
  customerCode: string;
  customerName: string;
  total: string;
  capturedAt: string;
  commissionId: number | null;
  itemCount: number;
  items?: Item[];
  header?: Record<string, unknown>;
};
export type Adjustment = {
  id: number;
  customerCode: string;
  type: string;
  amount: string;
  reason: string;
  sourceReference: string;
  sourceSnapshot?: Record<string, unknown> | null;
  commissionId: number | null;
  createdAt: string;
};
export type Payment = {
  calculatedAmount: string;
  manualAmount: boolean;
  manualReason: string | null;
  winthor: {
    routine: string;
    recnum: string;
    verification: string;
    details: { recnum: string; records: Record<string, unknown>[] } | null;
  } | null;
  amount: string;
  paidAt: string;
  confirmedAt: string;
  confirmedBy: string;
  verification: string;
  notes: string;
};
export type CommissionSquare = {
  code: number;
  name: string;
  type: "psd" | "pscf";
  psdSquare: number;
  pscfSquare: number;
  psdRegion: number | null;
  pscfRegion: number | null;
};
export type CommissionChecks = {
  customerCode: string;
  checkedAt: string;
  overdueTotal: string;
  returnsFound: number;
  fingerprint: string;
  overdueTitles: {
    customerCode: string;
    customerName: string;
    invoiceNumber: string;
    transaction: string;
    installment: string;
    dueDate: string;
    originalDueDate: string | null;
    overdueDays: number;
    collectionCode: string;
    amount: string;
  }[];
  returns: {
    transaction: string;
    invoiceNumber: string;
    customerCode: string;
    customerName: string;
    date: string | null;
    orderNumbers: string[];
    deductionAmount: string | null;
    selected: boolean;
    items?: {
      orderNumber: string;
      productCode: string;
      description: string;
      quantity: string;
      paymentStatus: "paid" | "not_found" | "unmatched";
      paidCommissions: {
        commissionId: number;
        commissionCode: string;
        mode: "normal" | "atg";
        paidAt: string;
      }[];
    }[];
  }[];
};
export type Commission = {
  id: number;
  mode: "normal" | "atg";
  code: string;
  customerCode: string;
  customerName: string;
  grossAmount: string;
  deductions: string;
  netAmount: string;
  status: string;
  createdAt: string;
  createdBy: { id: number; name: string };
  approvedBy: string | null;
  approvedAt: string | null;
  rejectionReason?: string | null;
  rejectedAt?: string | null;
  rejectedBy?: string | null;
  calculation?: {
    square?: number;
    checks?: CommissionChecks;
    version: string;
    reason: string;
    rule?: CommissionRule;
    mode?: string;
    percentageApplied?: string;
    effectiveBasis?: string;
    sales?: string;
    reference?: string;
    deductedFreight?: string;
    baseAmount?: string;
    items?: CalculationLine[];
    orders?: CalculationOrder[];
  };
  orders?: Order[];
  payment?: Payment;
  adjustments?: Adjustment[];
};
export type Audit = {
  id: number;
  actor: string;
  action: string;
  subject: string;
  details: Record<string, unknown>;
  requestId: string;
  ip: string;
  createdAt: string;
};
export type Summary = {
  totals: { count: string; gross: string; deductions: string; paid: string; outstanding: string };
  byStatus: { status: string; count: string; amount: string }[];
  byCustomer: { customer_code: string; customer_name: string; count: string; amount: string }[];
  monthly: { month: string; amount: string }[];
};

export type PriceContext = {
  comparisonSquare?: number;
  branch: string;
  orderRegion: number;
  psdRegion: number;
  pscfRegion: number;
};
export type CalculationOrder = {
  orderNumber: string;
  sales: string;
  reference: string;
  deductedFreight: string;
  baseAmount: string;
  grossAmount: string;
};
export type CalculationLine = {
  description?: string;
  unit?: string;
  paymentPlan?: string | number | null;
  discountPercentage?: string;
  percentageApplied?: string;
  commissionBeforeFreight?: string;
  commissionAmount?: string;
  allocatedFreight?: string;
  roundingAdjustment?: string;
  combo?: {
    components: {
      productCode: string;
      quantityPerCombo: string;
      unitPsd: string;
      unitPscf: string;
    }[];
  } | null;
  orderNumber: string;
  productCode: string;
  quantity: string;
  unitSale: string;
  unitReference: string;
  unitPsd?: string | null;
  unitPscf?: string | null;
  unitTable?: string | null;
  sales: string;
  reference: string;
  margin?: string;
  priceColumn?: string;
  context?: PriceContext;
};
export type CommissionRule = {
  version: number;
  percentage: string;
  basis: "margin_psd" | "margin_table" | "sales";
  psdRegion?: number | null;
  priceContexts?: PriceContext[] | null;
  returnPercentage?: string | null;
  atgReturnPercentage?: string | null;
  atgPercentage?: string | null;
  subtractFreight: boolean;
  applyReferenceDiscount: boolean;
  reason: string;
  createdAt: string;
  createdBy: string | null;
};
export type CalculationPreview = {
  checks: CommissionChecks;
  adjustmentIds: number[];
  adjustments: Adjustment[];
  calculation: {
    rule: CommissionRule;
    mode: string;
    percentageApplied: string;
    effectiveBasis: string;
    sales: string;
    reference: string;
    deductedFreight: string;
    baseAmount: string;
    grossAmount: string;
    items: CalculationLine[];
    orders: CalculationOrder[];
  };
  gross: string;
  deductions: string;
  net: string;
};
