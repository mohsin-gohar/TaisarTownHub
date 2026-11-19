import { chromium } from 'playwright';

const BASE = process.env.QA_BASE_URL || 'http://127.0.0.1:4173';
const email = `qa-contact-${Date.now()}@audit.test`;

const browser = await chromium.launch();
const page = await browser.newPage();
const pageErrors: string[] = [];
page.on('pageerror', (e) => pageErrors.push(String(e)));

await page.goto(`${BASE}/contact`, { waitUntil: 'networkidle' });
await page.waitForTimeout(1200);

await page.getByPlaceholder('e.g. Tariq Siddiqui').fill('QA Contact Probe');
await page.getByPlaceholder('e.g. +92 300 1234567').fill('+92 300 9998877');
await page.getByPlaceholder('e.g. investor@gmail.com').fill(email);
await page.getByPlaceholder('e.g. Sector 70-D, 120 Sq Yd West Open Plot').fill('Sector 70-D / QA probe');
await page.getByPlaceholder('Please mention your preferred location').fill('Automated inquiry honesty probe from qa-contact.mts');

await page.getByRole('button', { name: /Submit Official Inquiry/ }).click();
await page.waitForTimeout(2500);

const successVisible = await page.getByText('Inquiry Registered Successfully!').count();
const toastVisible = await page.getByText('dispatched to our Taiser Town advisory desk').count();

console.log(JSON.stringify({
  email,
  successState: successVisible > 0,
  toast: toastVisible > 0,
  pageErrors,
}, null, 2));

await browser.close();
console.log('QA_CONTACT_EMAIL=' + email);
