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

test('mobile viewport: dashboard renders at 390x844', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  const errors = collectErrors(page);
  await login(page, 'qa-companyadmin@example.com');
  await expect(page.locator('.fi-topbar, .fi-header').first()).toBeVisible();
  expect(errors).toEqual([]);
  await context.close();
});
