import { test } from 'node:test';
import assert from 'node:assert/strict';
import { csvEscape, rowsToCsv } from './exportCsv';

test('rowsToCsv writes a header line and one line per row', () => {
  const csv = rowsToCsv([
    { id: 1, name: 'A Ali', empty: null, n: 2.5 },
  ]);

  assert.match(csv, /^id,name,empty,n\r\n1,A Ali,,2\.5\r\n$/);
});

test('rowsToCsv returns empty text for an empty list', () => {
  assert.equal(rowsToCsv([]), '');
});

test('csvEscape quotes commas, quotes and newlines; leaves plain text alone', () => {
  assert.equal(csvEscape('a"b'), '"a""b"');
  assert.equal(csvEscape('a,b'), '"a,b"');
  assert.equal(csvEscape('a\nb'), '"a\nb"');
  assert.equal(csvEscape('a\rb'), '"a\rb"');
  assert.equal(csvEscape('plain'), 'plain');
  assert.equal(csvEscape(''), '');
});

test('csvEscape quotes leading/trailing spaces around a value', () => {
  assert.equal(csvEscape(' padded '), '" padded "');
  assert.equal(csvEscape('12'), '12');
});
