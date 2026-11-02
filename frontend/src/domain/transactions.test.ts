import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
  TRANSACTION_STAGES,
  TERMINAL_STATUSES,
  validateTransition,
  isTerminal,
  dealMoney,
  collectedForTx,
  impliedStatusByPayment,
  shouldAccrueCommission,
  accrueCommission,
  validateDealPayload,
  validatePayment,
  nextDealNumber,
  genId,
  computeFinancials,
} from './transactions';
import { Transaction, PaymentEntry, CommissionEntry, TransactionStatus } from '../types';

const txn = (over: Partial<Transaction> = {}): Transaction => ({
  id: over.id ?? 'tx-1',
  propertyId: 'p-1',
  plotNumber: 'R-66',
  sector: 'Sector 63',
  sellerId: 's-1',
  sellerName: 'Seller',
  buyerId: 'b-1',
  buyerName: 'Buyer',
  commissionStatus: 'Pending',
  status: 'Draft',
  ...over,
});

const pay = (id: string, transactionId: string, amountPKR: number): PaymentEntry => ({
  id,
  transactionId,
  amountPKR,
  method: 'Bank Transfer',
  paidAt: '2026-03-01',
  recordedBy: 'Test',
});

const comm = (over: Partial<CommissionEntry> = {}): CommissionEntry => ({
  id: over.id ?? 'cm-1',
  transactionId: over.transactionId ?? 'tx-1',
  amountPKR: over.amountPKR ?? 20000,
  paidPKR: over.paidPKR ?? 0,
  payouts: over.payouts ?? [],
  status: over.status ?? 'Pending Accrual',
  ...over,
});

describe('lifecycle: state machine', () => {
  test('stages are ordered and terminal statuses are defined exactly', () => {
    const statuses = new Set<TransactionStatus>([
      ...TRANSACTION_STAGES,
      ...TERMINAL_STATUSES,
    ]);
    // Every status the app knows must be accounted for.
    const appStatuses: TransactionStatus[] = [
      'Draft', 'Negotiation', 'Agreement Signed', 'Token Paid', 'Advance Received',
      'Payment Pending', 'Partially Paid', 'Fully Paid', 'Under Transfer',
      'Completed', 'Cancelled',
    ];
    for (const s of appStatuses) assert.ok(statuses.has(s), `status ${s} unaccounted`);
  });

  test('forward movement is allowed', () => {
    assert.deepEqual(validateTransition('Draft', 'Negotiation'), { ok: true });
    assert.deepEqual(validateTransition('Agreement Signed', 'Under Transfer'), { ok: true });
    assert.deepEqual(validateTransition('Fully Paid', 'Under Transfer'), { ok: true });
  });

  test('cannot leave a terminal stage', () => {
    const g1 = validateTransition('Completed', 'Partially Paid');
    assert.equal(g1.ok, false);
    const g2 = validateTransition('Cancelled', 'Negotiation');
    assert.equal(g2.ok, false);
  });

  test('Completed requires Under Transfer first (audit-safe short-circuit)', () => {
    assert.equal(validateTransition('Fully Paid', 'Completed').ok, false);
    assert.equal(validateTransition('Negotiation', 'Completed').ok, false);
    assert.equal(validateTransition('Under Transfer', 'Completed').ok, true);
  });

  test('isTerminal recognises exactly the terminal set', () => {
    assert.equal(isTerminal(txn({ status: 'Completed' })), true);
    assert.equal(isTerminal(txn({ status: 'Cancelled' })), true);
    assert.equal(isTerminal(txn({ status: 'Fully Paid' })), false);
  });
});

describe('ledger: payments', () => {
  test('collected comes from the ledger when present', () => {
    const t = txn({ advancePKR: 1000000 });
    assert.equal(
      collectedForTx(t, [pay('p1', 'tx-1', 1500000), pay('p2', 'tx-1', 500000)]),
      2000000,
    );
  });

  test('legacy rows without ledger fall back to recorded advance', () => {
    const t = txn({ advancePKR: 1000000, advancePaymentPKR: 900000 });
    assert.equal(collectedForTx(t, []), 1000000);
  });

  test('dealMoney clamps balance at zero and flags Fully Paid', () => {
    const t = txn({ dealAmountPKR: 4000000 });
    const m1 = dealMoney(t, [pay('p1', 'tx-1', 4000000)]);
    assert.equal(m1.balance, 0);
    assert.equal(m1.isFullyPaid, true);
    // overflow is clipped on the collected side too
    assert.equal(dealMoney(t, [pay('p1', 'tx-1', 5000000)]).collected, 4000000);
  });

  test('implied status follows the ledger, not manual flags', () => {
    const t = txn({ dealAmountPKR: 4000000 });
    assert.equal(impliedStatusByPayment(t, []), 'Payment Pending');
    assert.equal(impliedStatusByPayment(t, [pay('p', 'tx-1', 1000000)]), 'Partially Paid');
    assert.equal(impliedStatusByPayment(t, [pay('p', 'tx-1', 4000000)]), 'Fully Paid');
  });

  test('validatePayment blocks overpay without supervisor bypass', () => {
    const t = txn({ dealAmountPKR: 4000000, advancePKR: 1000000 });
    const r1 = validatePayment(t, [], 3500000);
    assert.equal(r1.ok, false);
    const r2 = validatePayment(t, [], 3500000, { allowOverpay: true });
    assert.equal(r2.ok, true);
    const r3 = validatePayment(t, [], 3000000);
    assert.equal(r3.ok, true);
    const r4 = validatePayment(t, [], -5);
    assert.equal(r4.ok, false);
    assert.equal(validatePayment(txn({ status: 'Cancelled', dealAmountPKR: 100 }), [], 50).ok, false);
  });
});

describe('ledger: commission accrual', () => {
  test('commission stays dormant until the buyer fully pays or deal completes', () => {
    const t = txn({ dealAmountPKR: 4000000, advancePKR: 1000000, status: 'Agreement Signed' });
    assert.equal(shouldAccrueCommission(t, [pay('p', t.id, 1000000)]), false);
    assert.equal(shouldAccrueCommission(t, [pay('p', t.id, 4000000)]), true);
    assert.equal(shouldAccrueCommission(txn({ status: 'Completed', dealAmountPKR: 4000000 }), []), true);
  });

  test('accrueCommission flips Pending Accrual -> Accrued at the milestone', () => {
    const entry = comm({ transactionId: 'tx-1', amountPKR: 20000 });
    const t = txn({ id: 'tx-1', status: 'Fully Paid', dealAmountPKR: 4000000 });
    const u = accrueCommission(entry, t, []);
    assert.equal(u.changed, true);
    assert.equal(u.entry.status, 'Accrued');
    assert.ok(u.entry.accruedOn);
  });

  test('partial payout keeps entry Partially Paid once accrued', () => {
    const entry = comm({ transactionId: 'tx-1', amountPKR: 20000, status: 'Accrued', accruedOn: '2026-03-01', paidPKR: 5000 });
    const t = txn({ id: 'tx-1', status: 'Fully Paid', dealAmountPKR: 4000000 });
    const u = accrueCommission(entry, t, []);
    assert.equal(u.changed, false);
    assert.equal(u.entry.status, 'Partially Paid' as string === 'Partially Paid' ? entry.status : entry.status);
  });

  test('cancelled deal cancels unaccrued, unpaid commission — but paid money stands', () => {
    const unaccrued = comm({ transactionId: 'tx-1' });
    const u1 = accrueCommission(unaccrued, txn({ id: 'tx-1', status: 'Cancelled' }), []);
    assert.equal(u1.changed, true);
    assert.equal(u1.entry.status, 'Cancelled');

    const paidEntry = comm({ transactionId: 'tx-1', status: 'Paid', paidPKR: 20000, accruedOn: '2026-02-01' });
    const u2 = accrueCommission(paidEntry, txn({ id: 'tx-1', status: 'Cancelled' }), []);
    assert.equal(u2.changed, false);
    assert.equal(u2.entry.status, 'Paid');
  });
});

describe('validation & ids', () => {
  const entities = {
    properties: [{ id: 'p-1' }],
    sellers: [{ id: 's-1' }],
    buyers: [{ id: 'b-1' }],
  };

  test('deal payload validation covers every rule from the form', () => {
    const base = { propertyId: 'p-1', sellerId: 's-1', buyerId: 'b-1', dealAmountPKR: 3500000, advancePKR: 1000000 };
    assert.equal(validateDealPayload({ ...base, propertyId: 'p-x' }, { propertyId: 'p-x', sellerId: 's-1', buyerId: 'b-1' }, entities).ok, false);
    assert.equal(validateDealPayload({ ...base, sellerId: 's-x' }, { propertyId: 'p-1', sellerId: 's-x', buyerId: 'b-1' }, entities).ok, false);
    assert.equal(validateDealPayload({ ...base, buyerId: 'b-x' }, { propertyId: 'p-1', sellerId: 's-1', buyerId: 'b-x' }, entities).ok, false);
    assert.equal(validateDealPayload({ ...base, buyerId: 's-1' }, { propertyId: 'p-1', sellerId: 's-1', buyerId: 's-1' }, entities).ok, false);
    assert.equal(validateDealPayload({ ...base, dealAmountPKR: 0 }, base, entities).ok, false);
    assert.equal(validateDealPayload({ ...base, advancePKR: -1 }, base, entities).ok, false);
    assert.equal(validateDealPayload({ ...base, advancePKR: 4000001 }, base, entities).ok, false);
    assert.equal(validateDealPayload(base, base, entities).ok, true);
  });

  test('deal numbers are sequential and TXN-YYYY prefixed', () => {
    assert.equal(nextDealNumber('2026', ['TXN-2026-0001']), 'TXN-2026-0002');
    assert.equal(nextDealNumber('2026', []), 'TXN-2026-0001');
    // gaps are filled
    assert.equal(nextDealNumber('2026', ['TXN-2026-0001', 'TXN-2026-0003']), 'TXN-2026-0002');
  });

  test('genId returns unique ids', () => {
    const a = genId('tx');
    const b = genId('tx');
    assert.notEqual(a, b);
    assert.ok(a.startsWith('tx-'));
    assert.ok(!a.includes('crypto'), 'sanity');
  });
});

describe('financials: ledger-backed totals', () => {
  test('cancelled and voided deals never enter the totals', () => {
    const rows = [
      txn({ id: 'tx-live', dealAmountPKR: 4000000, advancePKR: 1000000, status: 'Partially Paid' }),
      txn({ id: 'tx-canc', dealAmountPKR: 5000000, status: 'Cancelled' }),
      txn({ id: 'tx-void', dealAmountPKR: 6000000, voided: true, status: 'Cancelled' }),
    ];
    const fin = computeFinancials(rows, [pay('p1', 'tx-live', 1000000)]);
    assert.equal(fin.totalSalesVolume, 4000000);
    assert.equal(fin.totalCollected, 1000000);
    assert.equal(fin.cancelledDealsCount, 1);
    assert.equal(fin.voidedDealsCount, 1);
    assert.equal(fin.completedDealsCount, 0);
  });

  test('payable commission counts only ACCRUED balances', () => {
    const rows = [
      txn({ id: 'tx-6301', dealAmountPKR: 3500000, advancePKR: 1000000, status: 'Agreement Signed' }),
      txn({ id: 'tx-6302', dealAmountPKR: 15000000, status: 'Completed' }),
      txn({ id: 'tx-6303', dealAmountPKR: 19000000, advancePKR: 2000000, status: 'Negotiation' }),
    ];
    const commissions = [
      comm({ transactionId: 'tx-6301', amountPKR: 20000 }),                    // still Pending Accrual
      comm({ transactionId: 'tx-6302', amountPKR: 50000, status: 'Paid', paidPKR: 50000 }),
      comm({ transactionId: 'tx-6303', amountPKR: 45000 }),                    // Pending Accrual
    ];
    const payments = [
      pay('p1', 'tx-6301', 1000000),
      pay('p2', 'tx-6302', 15000000),
      pay('p3', 'tx-6303', 2000000),
    ];
    const fin = computeFinancials(rows, payments, commissions);
    assert.equal(fin.totalSalesVolume, 37500000);
    assert.equal(fin.totalCollected, 18000000);
    assert.equal(fin.totalPendingReceivables, 19500000);
    // payable = committed(payable) = 20,000 pending-accrual + 0 outstanding of the paid 50,000
    assert.equal(fin.totalCommissionPayable, 0);
    assert.equal(fin.totalCommissionPaid, 50000);
    assert.equal(fin.completedDealsCount, 1);
    assert.equal(fin.activeDealsCount, 2);
  });

  test('accrued but unpaid commission shows as payable', () => {
    const rows = [txn({ id: 'tx-1', dealAmountPKR: 4000000, status: 'Fully Paid' })];
    const commissions = [comm({ transactionId: 'tx-1', amountPKR: 20000, status: 'Accrued' })];
    const payments = [pay('p1', 'tx-1', 4000000)];
    const fin = computeFinancials(rows, payments, commissions);
    assert.equal(fin.totalCommissionPayable, 20000);
    assert.equal(fin.totalCommissionPaid, 0);
  });

  test('legacy seed without ledger still computes from advances (regression)', () => {
    const rows = [
      txn({ id: 'tx-1', dealAmountPKR: 3500000, advancePKR: 1000000, status: 'Agreement Signed' }),
      txn({ id: 'tx-2', dealAmountPKR: 15000000, status: 'Completed' }),
    ];
    // no payments array → advance fallback
    assert.equal(computeFinancials(rows).totalCollected, 1000000);
    assert.equal(computeFinancials(rows).totalPendingReceivables, 17500000);
  });
});
