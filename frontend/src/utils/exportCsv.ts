export type CsvRow = Record<string, string | number | boolean | null | undefined>;

/** RFC-4180 style escaping: quotes, commas, newlines and leading/trailing spaces become quoted fields. */
export function csvEscape(value: string): string {
  if (/[",\r\n]/.test(value) || value !== value.trim()) {
    return `"${value.replace(/"/g, '""')}"`;
  }
  return value;
}

/** Serializes rows (objects) to CSV text. First row defines the header columns. */
export function rowsToCsv(rows: CsvRow[]): string {
  const keys = Object.keys(rows[0] ?? {});
  if (keys.length === 0) return '';

  const lines = [keys.map((k) => csvEscape(k)).join(',')];
  for (const row of rows) {
    lines.push(keys.map((k) => csvEscape(String(row[k] ?? ''))).join(','));
  }
  return lines.join('\r\n') + '\r\n';
}

/** Triggers a browser download of the CSV produced by `rowsToCsv`. */
export function downloadCsv(filename: string, rows: CsvRow[]): boolean {
  const csv = rowsToCsv(rows);
  if (!csv) return false;

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
  return true;
}

/** Convenience wrapper used by export screens. */
export function exportCsv(filename: string, rows: CsvRow[]): boolean {
  return downloadCsv(filename, rows);
}
