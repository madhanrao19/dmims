// System-wide UI/UX production-readiness sweep. Requires local server + QA users:
//   php artisan serve & php artisan db:seed --class=QASampleUsersSeeder
// ponytail: one automated pass over every resource x every role beats hand-
// clicking hundreds of combinations; grep-based code review (see audit
// report) covers what a status-code/overflow sweep can't judge.
import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { login, collectErrors, ROLES } from './qa-helpers.js';

test.beforeEach(() => {
  execSync('php artisan cache:clear', { stdio: 'ignore' });
});

// Every Filament resource slug, from `php artisan route:list --path=admin`.
const RESOURCE_SLUGS = [
  'audit-logs', 'backups', 'barcode-registries', 'billing-records', 'boxes',
  'categories', 'customer-modules', 'customer-subscriptions', 'customers',
  'document-files', 'document-movement-logs', 'document-types', 'exports',
  'imports', 'license-logs', 'licenses', 'location-types', 'locations',
  'modules', 'notifications', 'product-location-stocks', 'products',
  'settings', 'stock-adjustment-approvals', 'stock-alerts',
  'stock-movements', 'subscription-plans', 'support-access-logs', 'users',
];

async function hasHorizontalOverflow(page) {
  return page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1
  );
}

for (const [role, email] of ROLES) {
  test.describe(`audit: ${role}`, () => {
    test('sweep list/create pages for status, overflow, console errors', async ({ page }) => {
      // 29 resources x 2 pages sequentially; Super Admin sees every one and
      // sits right at the 60s default, causing flaky timeouts.
      test.setTimeout(120000);
      const errors = collectErrors(page);
      await login(page, email);

      for (const slug of RESOURCE_SLUGS) {
        for (const suffix of ['', '/create']) {
          const path = `/admin/${slug}${suffix}`;
          const res = await page.goto(path);
          const status = res.status();
          if (![200, 403, 404].includes(status)) {
            errors.push(`${path}: unexpected HTTP ${status}`);
            continue;
          }
          if (status !== 200) continue;

          if (await hasHorizontalOverflow(page)) {
            errors.push(`${path}: horizontal overflow at desktop viewport`);
          }
          await page
            .screenshot({
              path: `test-results/audit/${role.replace(/\s+/g, '-')}__${slug}${suffix.replace('/', '-')}.png`,
              fullPage: true,
            })
            .catch(() => {});
        }
      }

      expect(errors).toEqual([]);
    });

    test('nav shell has no overflow at mobile and tablet viewports', async ({ browser }) => {
      for (const viewport of [{ width: 390, height: 844 }, { width: 768, height: 1024 }]) {
        const context = await browser.newContext({ viewport });
        const page = await context.newPage();
        const errors = collectErrors(page);
        await login(page, email);
        await expect(page.locator('.fi-topbar, .fi-header').first()).toBeVisible();
        if (await hasHorizontalOverflow(page)) {
          errors.push(`dashboard: horizontal overflow at ${viewport.width}x${viewport.height}`);
        }
        expect(errors).toEqual([]);
        await context.close();
      }
    });
  });
}
