/**
 * ERP seed data — ADMIN / DEMO CHUNK ONLY.
 *
 * This module is dynamically imported only in demo mode. In production/public
 * builds it is aliased to an empty module via vite.config.ts so that no demo
 * credentials, CNICs, or private ERP fields ever ship in any bundle.
 *
 * All demo account passwords are loaded from VITE_DEMO_* environment variables
 * at runtime; they are NEVER hardcoded here.
 */
export interface DemoAccount {
  role: string;
  name: string;
  title: string;
  email: string;
  password: string;
}

export const DEMO_ACCOUNTS: DemoAccount[] = [];

export const initialProperties: any[] = [];
export const initialInquiries: any[] = [];
export const initialSellers: any[] = [];
export const initialBuyers: any[] = [];
export const initialDealers: any[] = [];
export const initialTransactions: any[] = [];
export const initialPaymentEntries: any[] = [];
export const initialCommissionEntries: any[] = [];
export const initialPropertyFiles: any[] = [];
export const initialAuthUsers: any[] = [];
