/**
 * Transaction lifecycle domain — Phase 3 (P1-1…P1-5).
 *
 * The canonical state machine for deals, the payment ledger math, commission
 * accrual milestones and deal-form validation all live here so the UI can only
 * ever re-shape data through validated transitions.
 *
 * Lifecycle (ascending):
 *   Draft → Negotiation → Agreement Signed → Token Paid → Advance Received →
 *   Payment Pending → Partially Paid → Fully Paid → Under Transfer → Completed
 * Exits: Cancelled (any non-terminal stage, reason REQUIRED), and Void (audit
 * trail, super-admin-only, replaces hard delete).
 */
import { Transaction, TransactionStatus, PaymentEntry, CommissionEntry } from '../types';

/** Canonical stage order (index = hierarchy). */
export const TRANSACTION_STAGES: readonly TransactionStatus[] = [
  'Draft',
  'Negotiation',
  'Agreement Signed',
  'Token Paid',
  'Advance Received',
  'Payment Pending',
  'Partially Paid',
  'Fully Paid',
  'Under Transfer',
  'Completed',
];

export const TERMINAL_STATUSES: readonly TransactionStatus[] = ['Completed', 'Cancelled'];

/** Human-readable stage labels for UI (dropdowns, badges, receipts). */
export const STATUS_LABELS: Record<TransactionStatus, string> = {
  Draft: 'Draft',
  Negotiation: 'Negotiation',
  'Agreement Signed': 'Agreement Signed',
  'Token Paid': 'Token Paid',
  'Advance Received': 'Advance Received',
  'Payment Pending': 'Payment Pending',
  'Partially Paid': 'Partially Paid',
  'Fully Paid': 'Fully Paid',
  'Under Transfer': 'Under Transfer',
  Completed: 'Completed',
  Cancelled: 'Cancelled',
};

/**
 * Statuses that legitimately carry collected cash beyond the token/advance.
 * Used for finance aggregation (only count money actually banked).
 */
const PAID_STAGES = new Set<TransactionStatus>([
  'Partially Paid', 'Fully Paid', 'Under Transfer', 'Completed',
]);

export function isTerminal(txn: Pick<Transaction, 'status'> | TransactionStatus): boolean {
  const status = typeof txn === 'string' ? txn : txn.status;
  return TERMINAL_STATUSES.includes(status);
}

/** Stage index (−1 for unknown statuses such as Cancelled). */
export function stageIndex(status: TransactionStatus): number {
  return TRANSACTION_STAGES.indexOf(status);
}

export type TransitionResult =
  | { ok: true; error?: never }
  | { ok: false; error: string };

/**
 * Validate a status transition. Forward movement is always allowed; moving
 * backwards is allowed only within neighbouring stages (corrections), never
 * out of a terminal stage.
 */
export function validateTransition(
  from: TransactionStatus,
  to: TransactionStatus,
): TransitionResult {
  if (from === to) return { ok: true };
  if (isTerminal(from)) {
    return { ok: false, error: `Deal is ${from.toLowerCase()} — the record is read-only for bookkeeping.` };
  }
  if (isTerminal(to) && to === 'Completed') {
    // Completed must generally be reached via Under Transfer; allow direct
    // completion for legacy rows only from Under Transfer itself.
    if (from !== 'Under Transfer') {
      return { ok: false, error: 'A deal can only be marked Completed after it is Under Transfer at MDA.' };
    }
    return { ok: true };
  }

  const fromIndex = stageIndex(from);
  const toIndex = stageIndex(to);
  if (fromIndex >= 0 && toIndex >= 0 && toIndex < fromIndex - 1) {
    return { ok: false, error: 'A deal can only be corrected back by one lifecycle stage at a time.' };
  }

  return { ok: true };
}

export function validateVoid(
  txn: Transaction,
  payments: PaymentEntry[] = [],
  commissions: CommissionEntry[] = [],
): TransitionResult {
  if (txn.voided || txn.status === 'Completed' || txn.status === 'Cancelled') {
    return { ok: false, error: 'Completed, cancelled or already-voided deals cannot be voided again.' };
  }

  const advance = Math.max(0, Number(txn.advancePKR ?? 0), Number(txn.advancePaymentPKR ?? 0));
  if (advance > 0) {
    return { ok: false, error: 'Refund or reconcile the recorded advance before voiding this deal.' };
  }

  const netLedger = payments
    .filter((entry) => entry.transactionId === txn.id)
    .reduce((sum, entry) => {
      const isRefund = String(entry.type ?? '').toLowerCase().includes('refund');
      return sum + (isRefund ? -Math.abs(entry.amountPKR) : Math.abs(entry.amountPKR));
    }, 0);
  if (netLedger !== 0) {
    return { ok: false, error: 'Refund or reconcile all collected payments before voiding this deal.' };
  }

  const hasPaidCommission = commissions.some((entry) =>
    entry.transactionId === txn.id
      && (
        entry.status === 'Paid'
        || (entry.paidPKR ?? 0) > 0
        || (entry.payouts ?? []).some((payout) => payout.amountPKR > 0)
      ),
  );
  if (hasPaidCommission) {
    return { ok: false, error: 'A deal with a paid commission cannot be voided; use the audited commission reversal process.' };
  }

  return { ok: true };
}

/**
 * Money actually collected on a deal (P1-2). The payment ledger is the source
 * of truth when present; legacy seed rows without ledger entries fall back to
 * their recorded advance.
 */
export function collectedForTx(txn: Transaction, payments: PaymentEntry[] = []): number {
  const ledger = payments.filter((payment) => payment.transactionId === txn.id);
  const advance = txn.advancePKR ?? txn.advancePaymentPKR ?? 0;
  const legacyAdvance = Number.isFinite(advance) ? Math.max(0, advance) : 0;

  if (ledger.length === 0) return legacyAdvance;

  // Refunds are stored as negative amounts by the API, but older imports may
  // carry a positive amount with type=refund. Normalize both shapes and
  // subtract refunds from gross receipts instead of silently ignoring them.
  const grossPayments = ledger
    .filter((payment) => String(payment.type ?? '').toLowerCase() !== 'refund')
    .reduce((sum, payment) => sum + Math.abs(payment.amountPKR), 0);
  const refunds = ledger
    .filter((payment) => String(payment.type ?? '').toLowerCase() === 'refund')
    .reduce((sum, payment) => sum + Math.abs(payment.amountPKR), 0);
  const sourceGross = grossPayments > 0 ? grossPayments : legacyAdvance;

  return Math.max(0, sourceGross - refunds);
}

export interface DealMoney {
  dealAmount: number;
  collected: number;
  balance: number;
  isFullyPaid: boolean;
}

export function dealMoney(txn: Transaction, payments: PaymentEntry[] = []): DealMoney {
  const deal = txn.dealAmountPKR ?? txn.finalSellingAmountPKR ?? txn.agreementAmountPKR ?? 0;
  const dealAmount = Number.isFinite(deal) ? Math.max(0, deal) : 0;
  const collected = Math.min(
    collectedForTx(txn, payments),
    dealAmount, // overflow payments never inflate balance below zero
  );
  const balance = Math.max(0, dealAmount - collected);
  return { dealAmount, collected, balance, isFullyPaid: balance === 0 && dealAmount > 0 };
}

/** The transaction status implied purely by the collected money. */
export function impliedStatusByPayment(txn: Transaction, payments: PaymentEntry[] = []): TransactionStatus {
  const money = dealMoney(txn, payments);
  if (!money.isFullyPaid) {
    return money.collected > 0 ? 'Partially Paid' : 'Payment Pending';
  }
  return 'Fully Paid';
}

/** Commission accrual milestone (P1-3): commission becomes payable when the
 *  buyer has fully paid, or the deal is completed; not before. */
export function shouldAccrueCommission(
  txn: Transaction,
  payments: PaymentEntry[] = [],
): boolean {
  if (txn.voided || txn.status === 'Cancelled') return false;
  const money = dealMoney(txn, payments);
  // Milestone: money fully banked per ledger, OR the deal itself is at/after
  // the fully-paid milestone by status.
  return money.isFullyPaid || txn.status === 'Fully Paid' || txn.status === 'Completed';
}

export type CommissionAccrualUpdate =
  | { entry: CommissionEntry; changed: false }
  | { entry: CommissionEntry; changed: true; note: string };

/**
 * Re-derive a commission entry's accrual state from the deal's current standing.
 * Accrual can never be reversed automatically out of Paid (payouts are money
 * that actually moved); cancellation marks the entry Cancelled.
 */
export function accrueCommission(
  entry: CommissionEntry,
  txn: Transaction,
  payments: PaymentEntry[] = [],
  today: string = new Date().toISOString().split('T')[0],
): CommissionAccrualUpdate {
  if (txn.voided || txn.status === 'Cancelled') {
    if (entry.status !== 'Cancelled' && entry.paidPKR <= 0) {
      return { entry: { ...entry, status: 'Cancelled' }, changed: true, note: 'Deal closed without completion — commission accrual cancelled.' };
    }
    return { entry, changed: false };
  }
  if (!entry.accruedOn && shouldAccrueCommission(txn, payments)) {
    const changedStatus = entry.status === 'Pending Accrual';
    return {
      entry: {
        ...entry,
        status: entry.paidPKR >= entry.amountPKR && entry.amountPKR > 0 ? 'Paid' : changedStatus ? 'Accrued' : entry.status,
        accruedOn: today,
      },
      changed: true,
      note: `Commission of ${entry.amountPKR.toLocaleString()} accrued as the deal reached ${STATUS_LABELS[txn.status]}.`,
    };
  }
  return { entry, changed: false };
}

export interface DealPayload {
  propertyId: unknown;
  sellerId: unknown;
  buyerId: unknown;
  dealAmountPKR: unknown;
  advancePKR: unknown;
}

export interface DealPayloadRef {
  propertyId?: string;
  sellerId?: string;
  buyerId?: string;
}

export type DealValidationResult =
  | { ok: true; error?: never }
  | { ok: false; error: string };

/**
 * Shared validation for the deal form and any programmatic deal creation
 * (ported from AdminTransactions so domain + UI stay in lock-step).
 */
export function validateDealPayload(
  payload: DealPayload,
  refs: DealPayloadRef,
  entities: { properties: Array<{ id: string }>; sellers: Array<{ id: string }>; buyers: Array<{ id: string }> },
): DealValidationResult {
  const prop = entities.properties.find((p) => p.id === refs.propertyId);
  if (!prop) return { ok: false, error: 'Please select a valid property for the deal.' };
  const seller = entities.sellers.find((s) => s.id === refs.sellerId);
  const buyer = entities.buyers.find((b) => b.id === refs.buyerId);
  if (!seller || !buyer) {
    return { ok: false, error: 'Please select both valid seller and buyer.' };
  }
  if (refs.sellerId === refs.buyerId) {
    return { ok: false, error: 'Seller and buyer cannot be the same record.' };
  }
  const amount = Number(payload.dealAmountPKR);
  if (!Number.isSafeInteger(amount) || amount <= 0) {
    return { ok: false, error: 'Agreed deal amount must be a positive whole-rupee PKR amount.' };
  }
  const advance = Number(payload.advancePKR ?? 0);
  if (!Number.isSafeInteger(advance) || advance < 0) {
    return { ok: false, error: 'Advance must be a non-negative whole-rupee PKR amount.' };
  }
  if (advance > amount) {
    return { ok: false, error: 'Advance deposit cannot exceed the agreed deal amount.' };
  }
  return { ok: true };
}

export type ValidatePaymentResult =
  | { ok: true; currentCollected: number; error?: never }
  | { ok: false; error: string };

/**
 * Validate a payment against the deal before it hits the ledger (P1-2).
 * `allowOverpay` is the approve_transactions bypass (supervisor override).
 */
export function validatePayment(
  txn: Transaction,
  payments: PaymentEntry[],
  amount: number,
  opts?: { allowOverpay?: boolean },
): ValidatePaymentResult {
  if (txn.voided) return { ok: false, error: 'This deal is voided — payments are frozen.' };
  if (txn.status === 'Cancelled') return { ok: false, error: 'This deal is cancelled — payments are frozen.' };
  const money = dealMoney(txn, payments);
  if (!Number.isSafeInteger(amount) || amount <= 0) {
    return { ok: false, error: 'Payment amount must be a positive whole-rupee PKR amount.' };
  }
  if (!opts?.allowOverpay && money.collected + amount > money.dealAmount) {
    return {
      ok: false,
      error: `Payment of ${amount.toLocaleString()} exceeds the outstanding balance of ${money.balance.toLocaleString()} on this deal.`,
    };
  }
  return { ok: true, currentCollected: money.collected };
}

/** TXN-YYYY-NNNN human reference (P1-4). */
export function buildDealNumber(txn: Transaction, year: string): string {
  const source = txn.transactionDate ?? txn.dealDate ?? '';
  const yyyy = /^\d{4}/.test(source) ? source.slice(0, 4) : year;
  return `TXN-${yyyy}`;
}

/** Sequential numbering with non-colliding daily fallback. */
export function nextDealNumber(
  year: string,
  existingNumbers: string[],
): string {
  const taken = new Set(existingNumbers);
  for (let n = 1; n < 10000; n++) {
    const candidate = `TXN-${year}-${String(n).padStart(4, '0')}`;
    if (!taken.has(candidate)) return candidate;
  }
  // Extremely unlikely: 9999 deals in a year — fall back to time-based suffix.
  return `TXN-${year}-${Date.now().toString().slice(-4)}`;
}

/** Unique record id — crypto.randomUUID with a safe fallback (P1-4). */
export function genId(prefix: string): string {
  const c = globalThis.crypto as Crypto | undefined;
  if (c && typeof c.randomUUID === 'function') {
    return `${prefix}-${c.randomUUID()}`;
  }
  // Fallback for environments without crypto.randomUUID.
  return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

export interface FinancialTotals {
  totalSalesVolume: number;
  totalCollected: number;
  totalPendingReceivables: number;
  totalCommissionAccrued: number;
  totalCommissionPaid: number;
  totalCommissionPayable: number;
  activeDealsCount: number;
  completedDealsCount: number;
  cancelledDealsCount: number;
  voidedDealsCount: number;
}

/**
 * Ledger-backed financials (P1-5). Collected cash comes from the payment
 * ledger; commission payable only counts ACCRUED commission (deals at/after the
 * accrual milestone), never pending-accrual promises. Cancelled and voided deals
 * are excluded from all totals.
 */
export function computeFinancials(
  transactions: Transaction[],
  payments: PaymentEntry[] = [],
  commissions: CommissionEntry[] = [],
): FinancialTotals {
  const totals: FinancialTotals = {
    totalSalesVolume: 0,
    totalCollected: 0,
    totalPendingReceivables: 0,
    totalCommissionAccrued: 0,
    totalCommissionPaid: 0,
    totalCommissionPayable: 0,
    activeDealsCount: 0,
    completedDealsCount: 0,
    cancelledDealsCount: 0,
    voidedDealsCount: 0,
  };

  const commissionsByTx = new Map<string, CommissionEntry[]>();
  for (const commission of commissions) {
    const entries = commissionsByTx.get(commission.transactionId) ?? [];
    entries.push(commission);
    commissionsByTx.set(commission.transactionId, entries);
  }

  for (const t of transactions) {
    if (t.voided) {
      totals.voidedDealsCount += 1;
      continue;
    }
    if (t.status === 'Cancelled') {
      totals.cancelledDealsCount += 1;
      continue;
    }

    const money = dealMoney(t, payments);
    totals.totalSalesVolume += money.dealAmount;
    totals.totalCollected += money.collected;
    totals.totalPendingReceivables += money.balance;

    if (t.status === 'Completed') totals.completedDealsCount += 1;
    else totals.activeDealsCount += 1;

    for (const entry of commissionsByTx.get(t.id) ?? []) {
      const isAccrued = ['Accrued', 'Partially Paid', 'Paid'].includes(entry.status);
      if (isAccrued) totals.totalCommissionAccrued += Math.max(0, entry.amountPKR);
      totals.totalCommissionPaid += Math.min(Math.max(0, entry.paidPKR), Math.max(0, entry.amountPKR));
      if (entry.status === 'Accrued' || entry.status === 'Partially Paid') {
        totals.totalCommissionPayable += Math.max(0, entry.amountPKR - entry.paidPKR);
      }
    }
  }

  return totals;
}
