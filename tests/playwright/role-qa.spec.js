// Role-based QA suite. Requires local server + QA users:
//   php artisan serve & php artisan db:seed --class=QASampleUsersSeeder
// ponytail: one representative flow per concern, not per-resource coverage;
// expand a section only when a gap is found there.
import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

const PASSWORD = 'password';

// Filament throttles logins at 5/min per IP; the suite performs ~9. Clearing
// the cache (the rate limiter store) between tests keeps the limiter intact
// in production while letting local QA log in freely.
test.beforeEach(() => {
  execSync('php artisan cache:clear', { stdio: 'ignore' });
});

// [role, email, canOpenSettings, canOpenStockCreate]
// Platform users (Super Admin, Management) have platform-wide READ scope, so
// both can open settings; only Super Admin can write anywhere.
const ROLES = [
  ['Datamation Super Admin', 'qa-superadmin@example.com', true, true],
  ['Datamation Management', 'qa-management@example.com', true, false],
  ['Company Admin', 'qa-companyadmin@example.com', false, true],
  ['Company Supervisor', 'qa-supervisor@example.com', false, true],
  ['Stock Inventory User', 'qa-stock@example.com', false, true],
  ['Document Tracking User', 'qa-document@example.com', false, false],
  ['Viewer', 'qa-viewer@example.com', false, false],
];

function collectErrors(page) {
  const errors = [];
  page.on('console', msg => {
    // 4xx resource loads are expected when probing restricted URLs.
    if (msg.type() === 'error' && !/status of 40\d/.test(msg.text())) {
      errors.push(`console: ${msg.text()}`);
    }
  });
  page.on('response', res => {
    if (res.status() >= 500) errors.push(`network ${res.status()}: ${res.url()}`);
  });
  return errors;
}

async function login(page, email) {
  await page.goto('/admin/login');
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/admin(?!\/login)/, { timeout: 15000 });
}

test('login page renders and unauthenticated /admin redirects to login', async ({ page }) => {
  const errors = collectErrors(page);
  await page.goto('/admin');
  await expect(page).toHaveURL(/login/);
  await expect(page.locator('input[type="email"]')).toBeVisible();
  expect(errors).toEqual([]);
});

test('invalid credentials are rejected', async ({ page }) => {
  await page.goto('/admin/login');
  await page.fill('input[type="email"]', 'qa-viewer@example.com');
  await page.fill('input[type="password"]', 'wrong-password');
  await page.click('button[type="submit"]');
  await expect(page.getByText(/credentials do not match|these credentials/i)).toBeVisible();
});

for (const [role, email, canOpenSettings, canWriteStock] of ROLES) {
  test.describe(role, () => {
    test('login, dashboard, permissions, logout', async ({ page }) => {
      const errors = collectErrors(page);

      await login(page, email);
      await expect(page).toHaveURL(/\/admin/);
      // Dashboard shell rendered (Filament sidebar + main content).
      await expect(page.locator('.fi-sidebar')).toBeVisible();

      // Restricted URL: settings is Super Admin only ('manage settings'/'view settings').
      const settingsRes = await page.goto('/admin/settings');
      if (canOpenSettings) {
        expect(settingsRes.status()).toBe(200);
      } else {
        expect([403, 404]).toContain(settingsRes.status());
      }

      // Stock write permission: category create page.
      const createRes = await page.goto('/admin/categories/create');
      if (canWriteStock) {
        expect(createRes.status()).toBe(200);
      } else {
        expect([403, 404]).toContain(createRes.status());
      }

      // Logout via Filament user menu.
      await page.goto('/admin');
      await page.locator('.fi-user-menu button, button.fi-user-menu-trigger').first().click();
      await page.getByText(/sign out|log ?out/i).first().click();
      await page.waitForURL(/login/, { timeout: 15000 });

      // Session really gone: /admin bounces back to login.
      await page.goto('/admin');
      await expect(page).toHaveURL(/login/);

      expect(errors).toEqual([]);
    });
  });
}

test('CRUD + validation: stock user manages a category', async ({ page }) => {
  const errors = collectErrors(page);
  await login(page, 'qa-stock@example.com');

  // Validation: empty submit is blocked (native required validation) and we
  // stay on the create page.
  await page.goto('/admin/categories/create');
  await page.getByRole('button', { name: /^create$/i }).click();
  await page.waitForTimeout(1000);
  await expect(page).toHaveURL(/\/admin\/categories\/create/);

  // Create. The Customer select only offers the user's own company
  // (tenant-scoped); the server derives customer_id regardless.
  const name = `QA Category ${Math.random().toString(36).slice(2, 8)}`;
  await page.locator('button.fi-select-input-btn').first().click();
  await page.locator('[class*="fi-select"] input[type="search"], .fi-select-input-search-ctn input').first().fill('Dat');
  // Tenant scope: only the user's own company may be offered.
  const options = page.locator('[role="listbox"] [role="option"]');
  await expect(options.filter({ hasText: 'Datamation Inventory Demo' })).toBeVisible();
  await expect(options.filter({ hasText: 'Other Corp' })).toHaveCount(0);
  await options.filter({ hasText: 'Datamation Inventory Demo' }).click();
  await page.getByLabel(/category name/i).first().fill(name);
  await page.getByRole('button', { name: /^create$/i }).click();
  await page.waitForURL(/\/admin\/categories(?!\/create)/, { timeout: 15000 });

  // Search finds it in the list.
  await page.goto('/admin/categories');
  await page.getByPlaceholder(/search/i).last().fill(name); // table search, not topbar global search
  await expect(page.getByText(name).first()).toBeVisible();

  expect(errors).toEqual([]);
});

test('Platform Customer 360: platform admin can browse every tab for a customer', async ({ page }) => {
  const errors = collectErrors(page);
  await login(page, 'qa-superadmin@example.com');

  await page.goto('/admin/customers');
  await page.getByText('Datamation Inventory Demo').first().click();
  await page.waitForURL(/\/admin\/customers\/\d+$/, { timeout: 15000 });

  for (const tab of ['users', 'modules', 'subscription', 'license', 'billing', 'audit-logs']) {
    const url = page.url().replace(/\/admin\/customers\/(\d+)$/, `/admin/customers/$1/${tab}`);
    const res = await page.goto(url);
    expect(res.status()).toBe(200);
  }

  expect(errors).toEqual([]);
});

test('Platform Customer 360: old standalone nav items are gone, routes still work', async ({ page }) => {
  await login(page, 'qa-superadmin@example.com');
  await page.goto('/admin');

  const sidebar = page.locator('.fi-sidebar');
  for (const label of ['Customer Subscriptions', 'Customer Modules', 'Licenses', 'Invoices', 'Locations']) {
    await expect(sidebar.getByRole('link', { name: label, exact: true })).toHaveCount(0);
  }
  // "Users" is ambiguous with Customer 360's own tab label elsewhere, but
  // the standalone top-level sidebar entry itself must be gone too.
  await expect(sidebar.getByRole('link', { name: 'Users', exact: true })).toHaveCount(0);
  // Platform Audit Logs is explicitly NOT consolidated — stays visible.
  await expect(sidebar.getByRole('link', { name: 'Audit Logs', exact: true })).toBeVisible();

  // Routes remain fully functional even with the nav entry hidden.
  await expect((await page.goto('/admin/users')).status()).toBe(200);
  await expect((await page.goto('/admin/billing-records')).status()).toBe(200);
  await expect((await page.goto('/admin/licenses')).status()).toBe(200);
  await expect((await page.goto('/admin/locations')).status()).toBe(200);
});

test('Platform Customer 360: Locations tab browses and adds scoped to the selected customer', async ({ page }) => {
  const errors = collectErrors(page);
  await login(page, 'qa-superadmin@example.com');

  await page.goto('/admin/customers');
  await page.getByText('Datamation Inventory Demo').first().click();
  await page.waitForURL(/\/admin\/customers\/\d+$/, { timeout: 15000 });
  await page.goto(page.url() + '/locations');
  await expect(page.getByRole('heading', { name: /customer locations/i })).toBeVisible({ timeout: 15000 });

  const code = `LOC-${Math.random().toString(36).slice(2, 8)}`;
  await page.getByRole('button', { name: /^add location$/i }).click();
  await expect(page.getByRole('heading', { name: /create location/i })).toBeVisible({ timeout: 15000 });
  // No customer picker in this modal — same Hidden-field trick as the other
  // Customer 360 "Add X" actions.
  await expect(page.getByText('Customer', { exact: true })).toHaveCount(0);
  await page.getByRole('textbox', { name: /^location code/i }).fill(code);
  await page.getByRole('textbox', { name: /^location name/i }).fill('QA New Warehouse');
  await page.getByRole('button', { name: /^create$/i }).click();
  await page.waitForTimeout(1500);

  await page.getByPlaceholder(/search/i).last().fill(code);
  await expect(page.getByText(code).first()).toBeVisible();

  expect(errors).toEqual([]);
});

test('Tenant Locations: Company Admin keeps their own standalone nav, no Customer field', async ({ page }) => {
  await login(page, 'qa-companyadmin@example.com');
  await expect(page.locator('.fi-sidebar').getByRole('link', { name: 'Locations', exact: true })).toBeVisible();

  await page.goto('/admin/locations/create');
  await expect(page.getByText('Customer', { exact: true })).toHaveCount(0);
});

test('Platform Customer 360: Add User creates a user scoped to the selected customer', async ({ page }) => {
  const errors = collectErrors(page);
  await login(page, 'qa-superadmin@example.com');

  await page.goto('/admin/customers');
  await page.getByText('Datamation Inventory Demo').first().click();
  await page.waitForURL(/\/admin\/customers\/\d+$/, { timeout: 15000 });
  await page.goto(page.url() + '/users');

  const email = `qa-new-${Math.random().toString(36).slice(2, 8)}@example.com`;
  await page.getByRole('button', { name: /^add user$/i }).click();
  await expect(page.getByRole('heading', { name: /create user/i })).toBeVisible({ timeout: 15000 });
  // No customer picker in this modal — it's a Hidden field fixed to the
  // customer already selected, not a browser-choosable dropdown.
  await expect(page.getByText('Customer', { exact: true })).toHaveCount(0);
  await page.getByRole('textbox', { name: /^name/i }).fill('QA New User');
  await page.getByRole('textbox', { name: /^email/i }).fill(email);
  await page.getByRole('textbox', { name: /^password/i }).fill('password123');
  await page.getByRole('combobox', { name: /^status/i }).selectOption('active');
  await page.getByRole('button', { name: /^create$/i }).click();
  await page.waitForTimeout(1500);

  await page.getByPlaceholder(/search/i).last().fill(email);
  await expect(page.getByText(email).first()).toBeVisible();

  expect(errors).toEqual([]);
});

test('Platform Customer 360: a non-platform role is denied even for their own customer', async ({ page }) => {
  await login(page, 'qa-companyadmin@example.com');

  // CustomerResource::can('view') legitimately passes for Company Admin on
  // their OWN customer record (that's what My Company relies on) — Customer
  // 360 must still deny via its platform-user-only gate, not resource-level
  // ownership. QASampleUsersSeeder assigns every QA user to the single
  // seeded DEMO customer (id 1 in a fresh QA seed run).
  for (const path of ['', '/users', '/modules', '/subscription', '/license', '/billing', '/audit-logs']) {
    const res = await page.goto(`/admin/customers/1${path}`);
    expect([403, 404]).toContain(res.status());
  }
});

test('mobile viewport: dashboard renders at 390x844', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  const errors = collectErrors(page);
  await login(page, 'qa-companyadmin@example.com');
  await expect(page.locator('.fi-topbar, .fi-header').first()).toBeVisible();
  expect(errors).toEqual([]);
  await context.close();
});
