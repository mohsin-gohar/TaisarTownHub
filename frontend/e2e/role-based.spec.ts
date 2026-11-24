import { expect, test, type APIRequestContext, type Page } from '@playwright/test';
import { DEMO_ROLE_IPS, loginToAdminViaApi, type DemoAuthUser } from './helpers/adminLogin';

/**
 * PHASE 5 — Role-based testing for demo accounts.
 * Verifies each role can authenticate and sees role-appropriate admin shell content.
 */

const ciDemoPassword = process.env.DEMO_STAFF_PASSWORD;
const DEMO_ACCOUNTS = [
  { role: 'super_admin', email: 'superadmin@taisartownhub.com', password: ciDemoPassword || 'Super@Admin2026!' },
  { role: 'agency_owner', email: 'admin@taisartownhub.com', password: ciDemoPassword || 'Admin@Taisar2026' },
  { role: 'manager', email: 'manager@taisartownhub.com', password: ciDemoPassword || 'Manager@2026' },
  { role: 'dealer', email: 'dealer@taisartownhub.com', password: ciDemoPassword || 'Dealer@2026' },
  { role: 'operator', email: 'operator@taisartownhub.com', password: ciDemoPassword || 'Operator@2026' },
] as const;

async function loginAs(page: Page, request: APIRequestContext, account: (typeof DEMO_ACCOUNTS)[number]): Promise<DemoAuthUser> {
  // Node-side API login with a per-role TEST-NET-3 bucket keeps the 10/min
  // shared-IP limiter from throttling unrelated parallel role tests. The
  // browser sign-in form itself is covered in admin-pages.spec.ts.
  return loginToAdminViaApi(page, request, account, DEMO_ROLE_IPS[account.role]);
}

test.describe('Role-Based Access - Every role gets correct permissions', () => {
  // Two SPA navigations per role under parallel load can exceed 30s on a
  // busy local stack; this is a timeout allowance, not a weaker assertion.
  test.slow();
  test.describe('Super Admin - Full platform access', () => {
    test('can access key admin sections', async ({ page, request }) => {
      const user = await loginAs(page, request, DEMO_ACCOUNTS[0]);
      expect(user.is_platform).toBe(true);
      expect(user.enterprise_permissions).toContain('admin.manage_agencies');

      await expect(page.getByRole('button', { name: /dashboard overview/i }).first()).toBeVisible();
      await expect(page.getByRole('button', { name: /security & roles/i }).first()).toBeVisible();
      await expect(page.getByRole('button', { name: /audit & recycle bin/i }).first()).toBeVisible();
      await expect(page.getByRole('button', { name: /agency approvals/i }).first()).toBeVisible();

      await page.getByRole('button', { name: /security & roles/i }).first().click();
      await expect(page.locator('main')).toContainText(/security|role|user|permission/i);

      await page.getByRole('button', { name: /audit & recycle bin/i }).first().click();
      await expect(page.locator('main')).toContainText(/audit|log|recycle|action/i);

      await page.getByRole('button', { name: /agency approvals/i }).first().click();
      await expect(page.locator('main')).toContainText(/agency|approval|verify|status|plan|subscription/i);
    });

    test('can access agency approval workspace', async ({ page, request }) => {
      await loginAs(page, request, DEMO_ACCOUNTS[0]);
      await page.getByRole('button', { name: /agency approvals/i }).first().click();
      await expect(page.locator('main')).toContainText(/agency/i);
    });
  });

  test.describe('Agency Owner - Agency-scoped access', () => {
    test('can access the agency workspace without platform scope', async ({ page, request }) => {
      const user = await loginAs(page, request, DEMO_ACCOUNTS[1]);
      expect(user.is_platform).toBe(false);
      expect(user.enterprise_permissions).not.toContain('admin.manage_agencies');
      expect(user.agencies?.[0]?.role_key).toBe('agency_owner');
      await expect(page).toHaveURL(/\/admin/);
      await expect(page.locator('main')).toBeVisible();
      await expect(page.getByRole('button', { name: /agency dashboard/i }).first()).toBeVisible();
      await expect(page.getByRole('button', { name: /staff & roles/i }).first()).toBeVisible();
    });
  });

  test.describe('Manager - Scoped access', () => {
    test('can access admin dashboard shell', async ({ page, request }) => {
      await loginAs(page, request, DEMO_ACCOUNTS[2]);
      await expect(page).toHaveURL(/\/admin/);
      await expect(page.locator('main')).toBeVisible();
    });
  });

  test.describe('Dealer - Scoped access', () => {
    test('can access admin dashboard shell', async ({ page, request }) => {
      await loginAs(page, request, DEMO_ACCOUNTS[3]);
      await expect(page).toHaveURL(/\/admin/);
      await expect(page.locator('main')).toBeVisible();
    });
  });

  test.describe('Operator - Limited access', () => {
    test('can access admin dashboard shell', async ({ page, request }) => {
      await loginAs(page, request, DEMO_ACCOUNTS[4]);
      await expect(page).toHaveURL(/\/admin/);
      await expect(page.locator('main')).toBeVisible();
    });
  });

  test.describe('Visitor / Guest - Public access only', () => {
    test('cannot access admin dashboard', async ({ page, request }) => {
      await page.goto('/admin');
      await page.waitForTimeout(2500);
      const hasSignIn = (await page.locator('text=/sign in|login/i').count()) > 0;
      const isLoginPage = /\/login|\/admin-login/.test(page.url());
      expect(hasSignIn || isLoginPage).toBe(true);
    });

    test('can browse key public pages', async ({ page, request }) => {
      await page.goto('/');
      await expect(page.locator('main')).toBeVisible();

      await page.getByRole('button', { name: /^properties$/i }).first().click();
      await expect(page).toHaveURL(/\/properties/, { timeout: 10000 });
      await expect(page.locator('main')).toBeVisible();

      await page.getByRole('button', { name: /^agencies$/i }).first().click();
      await expect(page).toHaveURL(/\/agencies/, { timeout: 10000 });
      await expect(page.locator('main')).toBeVisible();
    });
  });

  /**
   * Capability boundaries. The sidebar is a cosmetic reflection of the domain
   * permission matrix (backend/app/Support/RolePermissions.php and
   * backend/tests/Unit/RolePermissionsTest.php prove server enforcement). These
   * assertions catch a UI regression that would expose privileged desks to a
   * role which the API would still reject.
   */
  test.describe('Capability boundaries - sidebar matches the permission matrix', () => {
    const security = (page: Page) => page.getByRole('button', { name: /security & roles/i }).first();
    const audit = (page: Page) => page.getByRole('button', { name: /audit & recycle bin/i }).first();
    const approvals = (page: Page) => page.getByRole('button', { name: /agency approvals/i }).first();

    test('super admin sees platform-only desks', async ({ page, request }) => {
      await loginAs(page, request, DEMO_ACCOUNTS[0]);
      await expect(security(page)).toBeVisible();
      await expect(audit(page)).toBeVisible();
      await expect(approvals(page)).toBeVisible();
    });

    test('agency admin has no platform user, audit or approval desks', async ({ page, request }) => {
      await loginAs(page, request, DEMO_ACCOUNTS[1]);
      await expect(security(page)).toHaveCount(0);
      await expect(audit(page)).toHaveCount(0);
      await expect(approvals(page)).toHaveCount(0);
      // The global website CMS and platform dealer desk are not agency-owner tools.
      await expect(page.getByRole('button', { name: /website cms & builder/i }).first()).toHaveCount(0);
      await expect(page.getByRole('button', { name: /agency dashboard/i }).first()).toBeVisible();
      await expect(page.getByRole('button', { name: /staff & roles/i }).first()).toBeVisible();
      await expect(page.getByRole('button', { name: /dealers & commission/i }).first()).toHaveCount(0);
    });

    test('manager can verify files but cannot edit CMS or manage users', async ({ page, request }) => {
      await loginAs(page, request, DEMO_ACCOUNTS[2]);
      await expect(page.getByRole('button', { name: /file verification/i }).first()).toBeVisible();
      await expect(page.getByRole('button', { name: /deals & transactions/i }).first()).toBeVisible();
      await expect(security(page)).toHaveCount(0);
      await expect(page.getByRole('button', { name: /website cms & builder/i }).first()).toHaveCount(0);
    });

    test('dealer sees only own-deal desks, not reports or staff tools', async ({ page, request }) => {
      await loginAs(page, request, DEMO_ACCOUNTS[3]);
      await expect(page.getByRole('button', { name: /deals & transactions/i }).first()).toBeVisible();
      await expect(page.getByRole('button', { name: /property listings/i }).first()).toBeVisible();
      await expect(page.getByRole('button', { name: /dashboard overview/i }).first()).toHaveCount(0);
      await expect(page.getByRole('button', { name: /sellers & owners/i }).first()).toHaveCount(0);
      await expect(security(page)).toHaveCount(0);
    });

    test('operator can upload but cannot verify or open staff desks', async ({ page, request }) => {
      await loginAs(page, request, DEMO_ACCOUNTS[4]);
      await expect(page.getByRole('button', { name: /file verification/i }).first()).toBeVisible();
      await expect(page.getByRole('button', { name: /dashboard overview/i }).first()).toBeVisible();
      await expect(security(page)).toHaveCount(0);
      await expect(page.getByRole('button', { name: /media library/i }).first()).toHaveCount(0);
    });
  });
});
