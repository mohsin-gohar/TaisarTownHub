/**
 * Writes the current fictional bootstrap records to the Laravel seed file.
 * Run from frontend/: npx tsx scripts/export-mysql-seed.ts
 * The JSON is loaded by DatabaseSeeder into MySQL. It is not shipped in the public bundle.
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { initialCMSConfig, initialMedia, initialPlotMarkers, initialProjects, initialTestimonials } from '../src/data/publicSeed';
import { initialAgencyVideos } from '../src/data/agencyVideosSeed';
import {
  DEMO_ACCOUNTS,
  initialBuyers,
  initialCommissionEntries,
  initialDealers,
  initialInquiries,
  initialPaymentEntries,
  initialProperties,
  initialPropertyFiles,
  initialSellers,
  initialTransactions,
} from '../src/data/erpSeed';

const here = dirname(fileURLToPath(import.meta.url));
const target = resolve(here, '../../backend/database/seed-data/workspace.json');
mkdirSync(dirname(target), { recursive: true });

const staff = DEMO_ACCOUNTS.map((account) => ({
  name: account.name,
  email: account.email,
  password: account.password,
  role: account.role,
  dealer_id: account.role === 'dealer' ? 'deal-3' : null,
}));

const payload = {
  staff,
  projects: initialProjects,
  properties: initialProperties,
  dealers: initialDealers,
  sellers: initialSellers,
  buyers: initialBuyers,
  transactions: initialTransactions,
  payments: initialPaymentEntries,
  commissions: initialCommissionEntries,
  files: initialPropertyFiles,
  plots: initialPlotMarkers,
  media: initialMedia,
  videos: initialAgencyVideos,
  inquiries: initialInquiries,
  testimonials: initialTestimonials,
  cms: initialCMSConfig,
};

writeFileSync(target, JSON.stringify(payload));
console.log(`Wrote ${target}`);
