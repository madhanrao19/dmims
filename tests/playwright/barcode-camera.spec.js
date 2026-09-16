// Scanner-lifecycle regression coverage for the zxing-wasm swap
// (resources/js/barcode-camera.js). Playwright can't grant a real camera or
// feed it a live barcode (see docs/CONFORMANCE_GAP_ANALYSIS.md — on-device
// QA is still required for actual decode accuracy), so this mocks
// getUserMedia to cover exactly the risk this library swap introduced:
// open/error/close/reopen and that closing genuinely stops the media track,
// rather than testing decode correctness.
import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { login } from './qa-helpers.js';

// Same reasoning as row-actions.spec.js: EnsureSubscriptionActive's 60s
// cache key is just `subscription_active:{customerId}`, shared with
// whatever PHPUnit's RefreshDatabase feature tests wrote for the same
// customer id moments earlier — clear it so this run isn't 403'd by a
// stale negative cached against a fresh unrelated test's data.
test.beforeEach(() => {
  execSync('php artisan cache:clear', { stdio: 'ignore' });
});

// Stubs navigator.mediaDevices.getUserMedia before any page script runs, so
// barcode-camera.js's own feature-detect (`Boolean(getUserMedia)`) still
// sees a real function and renders the button. Records every call and every
// track .stop() so tests can assert on them via window.__cameraMock.
async function mockCamera(page, { rejectWith } = {}) {
  await page.addInitScript(({ rejectWith }) => {
    window.__cameraMock = { calls: 0, stopped: 0 };

    class FakeTrack {
      stop() { window.__cameraMock.stopped++; }
    }

    navigator.mediaDevices.getUserMedia = async () => {
      window.__cameraMock.calls++;
      if (rejectWith) {
        const e = new Error(rejectWith);
        e.name = rejectWith;
        throw e;
      }
      return { getTracks: () => [new FakeTrack()] };
    };
  }, { rejectWith });
}

test('scanner opens, video gets a stream, and closing stops the camera track', async ({ page }) => {
  await mockCamera(page);
  await login(page, 'qa-superadmin@example.com');
  await page.goto('/admin/boxes');

  const scanButton = page.getByRole('button', { name: 'Scan Barcode' }).first();
  await expect(scanButton).toBeVisible();
  await scanButton.click();

  await expect(page.locator('video[x-ref="video"]').first()).toBeVisible();
  expect(await page.evaluate(() => window.__cameraMock.calls)).toBe(1);

  await page.getByRole('button', { name: '×' }).first().click();

  await expect(page.locator('video[x-ref="video"]').first()).toBeHidden();
  expect(await page.evaluate(() => window.__cameraMock.stopped)).toBe(1);
});

test('scanner shows a clear error and stays open when the camera is denied', async ({ page }) => {
  await mockCamera(page, { rejectWith: 'NotAllowedError' });
  await login(page, 'qa-superadmin@example.com');
  await page.goto('/admin/boxes');

  await page.getByRole('button', { name: 'Scan Barcode' }).first().click();

  const modal = page.locator('div.fixed.inset-0');
  await expect(modal).toBeVisible();
  await expect(modal.getByText(/NotAllowedError|camera/i)).toBeVisible();
});

test('scanner can reopen (with a fresh camera grant) after closing, and on a different page', async ({ page }) => {
  await mockCamera(page);
  await login(page, 'qa-superadmin@example.com');
  await page.goto('/admin/boxes');

  const scanButton = page.getByRole('button', { name: 'Scan Barcode' }).first();
  await scanButton.click();
  await page.getByRole('button', { name: '×' }).first().click();
  await scanButton.click();
  expect(await page.evaluate(() => window.__cameraMock.calls)).toBe(2);
  await page.getByRole('button', { name: '×' }).first().click();

  // A full page navigation (this panel has no SPA/wire:navigate mode) tears
  // down all page JS state on its own; reopening on the new page should
  // still work rather than being left in some broken carried-over state.
  await page.goto('/admin/document-files');
  const scanButtonOnOtherPage = page.getByRole('button', { name: 'Scan Barcode' }).first();
  await expect(scanButtonOnOtherPage).toBeVisible();
  await scanButtonOnOtherPage.click();
  await expect(page.locator('video[x-ref="video"]').first()).toBeVisible();
});
