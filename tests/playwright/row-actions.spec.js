// Row/header-action modal sweep. Requires local server + QA users:
//   php artisan serve & php artisan db:seed --class=QASampleUsersSeeder
// ponytail: one pass as Super Admin (broadest access) opening every custom
// action modal app-wide, never submitting. This exists because a real bug
// (BarcodeRegistryResource's Preview/Print crashing on a live() select
// change — see docs/CONFORMANCE_GAP_ANALYSIS.md #14) shipped past
// uiux-audit.spec.js, which only exercises List/Create page loads and never
// clicks into a row-action modal. Header actions (no record needed) always
// run; row actions (need an existing record) skip gracefully when the QA
// seed hasn't created one — this keeps the spec robust across seed states
// while still catching real crashes whenever data is present.
import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { login, collectErrors } from './qa-helpers.js';

test.beforeEach(() => {
  execSync('php artisan cache:clear', { stdio: 'ignore' });
});

// [path, action button label, needsExistingRecord]
const ACTIONS = [
  ['/admin/boxes', /^transfer$/i, true],
  ['/admin/boxes', /^move out$/i, true],
  ['/admin/boxes', /^timeline$/i, true],
  ['/admin/document-files', /^transfer$/i, true],
  ['/admin/document-files', /^move out$/i, true],
  ['/admin/document-files', /^timeline$/i, true],
  ['/admin/stock-movements', /receive in/i, false],
  ['/admin/stock-movements', /stock out/i, false],
  ['/admin/stock-movements', /^transfer$/i, false],
  ['/admin/stock-movements', /^adjust$/i, false],
  ['/admin/billing-records', /record payment/i, true],
  ['/admin/billing-records', /^issue$/i, true],
  ['/admin/billing-records', /^cancel$/i, true],
  ['/admin/barcode-registries', /preview \/ print/i, true],
  ['/admin/barcode-registries', /lost\/damaged/i, true],
  ['/admin/barcode-registries', /batch generate/i, false],
  ['/admin/backups', /run database backup/i, false],
  ['/admin/exports', /new export/i, false],
  ['/admin/imports', /new import/i, false],
];

test('every custom action modal opens without crashing (never submitted)', async ({ page }) => {
  const errors = collectErrors(page);
  await login(page, 'qa-superadmin@example.com');

  const visited = new Set();
  for (const [path, labelPattern, needsRecord] of ACTIONS) {
    if (!visited.has(path)) {
      const res = await page.goto(path);
      expect(res.status(), `${path} should load`).toBe(200);
      visited.add(path);
    }

    const btn = page.getByRole('button', { name: labelPattern }).first();
    if (!(await btn.count())) {
      // Header action should always be present; a row action's absence
      // just means the QA seed hasn't created a record for it yet.
      expect(needsRecord, `${path} ${labelPattern} button should exist`).toBe(true);
      continue;
    }

    await btn.click();
    await page.waitForTimeout(700);
    await expect(page.getByText(/internal server error/i)).toHaveCount(0);
    expect(errors, `${path} ${labelPattern}`).toEqual([]);

    // If the modal exposes a live()-updating select (the exact class of bug
    // this spec guards against), exercise it before closing.
    const selects = page.locator('.fi-modal select, [role="dialog"] select');
    const selectCount = await selects.count();
    if (selectCount) {
      const first = selects.first();
      const optionValues = await first.locator('option').evaluateAll(opts => opts.map(o => o.value).filter(Boolean));
      if (optionValues.length > 1) {
        await first.selectOption(optionValues[1]);
        await page.waitForTimeout(700);
        await expect(page.getByText(/internal server error/i)).toHaveCount(0);
        expect(errors, `${path} ${labelPattern} after select change`).toEqual([]);
      }
    }

    await page.keyboard.press('Escape');
    await page.waitForTimeout(200);
  }
});
