/**
 * Media sale economics (dealer/agency file attribution) — regression tests.
 * Guards the profit/commission maths used by the dashboard "Mark as Sold"
 * flow: profit = selling - cost, default 30% agency margin, no negative
 * profit, and per-seller attribution aggregation.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { calculateMediaSaleProfit, summarizeMediaSales } from './logic';

test('calculateMediaSaleProfit: default 30% margin when cost is blank', () => {
  const r = calculateMediaSaleProfit(3_600_000);
  assert.equal(r.ok, true);
  assert.equal(r.sellingPrice, 3_600_000);
  assert.equal(r.costPrice, 2_520_000); // 70% of selling price
  assert.equal(r.profit, 1_080_000);
  assert.equal(r.marginPct, 30);
});

test('calculateMediaSaleProfit: explicit cost overrides the default margin', () => {
  const r = calculateMediaSaleProfit(1_000_000, 850_000);
  assert.equal(r.ok, true);
  assert.equal(r.profit, 150_000);
  assert.equal(r.marginPct, 15);
});

test('calculateMediaSaleProfit: rejects invalid selling prices', () => {
  assert.equal(calculateMediaSaleProfit(0).ok, false);
  assert.equal(calculateMediaSaleProfit(-5).ok, false);
  assert.equal(calculateMediaSaleProfit('x' as unknown as number).ok, false);
  assert.equal(calculateMediaSaleProfit(NaN).ok, false);
});

test('calculateMediaSaleProfit: rejects cost above selling price (no negative profit)', () => {
  const r = calculateMediaSaleProfit(100_000, 150_000);
  assert.equal(r.ok, false);
  assert.match(r.error ?? '', /cannot exceed/i);
});

test('calculateMediaSaleProfit: rounds fractional rupees', () => {
  const r = calculateMediaSaleProfit(999_999.6);
  assert.equal(r.sellingPrice, 1_000_000); // Math.round
  assert.equal(r.profit, 300_000);
});

test('summarizeMediaSales: aggregates files, value and profit per seller', () => {
  const rows = [
    { soldBy: 'Saleem Siddiqui', soldByRole: 'admin', soldPricePKR: 3_600_000, profitPKR: 1_080_000 },
    { soldBy: 'Saleem Siddiqui', soldByRole: 'admin', soldPricePKR: 1_000_000, profitPKR: 150_000 },
    { soldBy: 'Kamran Haider', soldByRole: 'manager', soldPricePKR: 500_000, profitPKR: 50_000 },
    { soldPricePKR: 999, profitPKR: 1 }, // unsold row must be ignored
  ];
  const summary = summarizeMediaSales(rows);
  assert.equal(summary.length, 2);
  // Sorted by profit desc
  assert.equal(summary[0].seller, 'Saleem Siddiqui');
  assert.equal(summary[0].filesSold, 2);
  assert.equal(summary[0].totalSaleValue, 4_600_000);
  assert.equal(summary[0].totalProfit, 1_230_000);
  assert.equal(summary[1].seller, 'Kamran Haider');
  assert.equal(summary[1].totalProfit, 50_000);
});

test('summarizeMediaSales: empty input yields empty summary', () => {
  assert.deepEqual(summarizeMediaSales([]), []);
  assert.deepEqual(summarizeMediaSales(undefined as unknown as []), []);
});