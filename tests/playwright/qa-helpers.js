// Shared helpers for role-qa.spec.js and uiux-audit.spec.js.
// ponytail: kept in a plain (non-.spec) module so importing it never
// re-executes another file's top-level test() calls.

// [role, email, canOpenSettings, canOpenStockCreate]
// Platform users (Super Admin, Management) have platform-wide READ scope, so
// both can open settings; only Super Admin can write anywhere.
export const ROLES = [
  ['Datamation Super Admin', 'qa-superadmin@example.com', true, true],
  ['Datamation Management', 'qa-management@example.com', true, false],
  ['Company Admin', 'qa-companyadmin@example.com', false, true],
  ['Company Supervisor', 'qa-supervisor@example.com', false, true],
  ['Stock Inventory User', 'qa-stock@example.com', false, true],
  ['Document Tracking User', 'qa-document@example.com', false, false],
  ['Viewer', 'qa-viewer@example.com', false, false],
];

const PASSWORD = 'password';

export function collectErrors(page) {
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

export async function login(page, email) {
  await page.goto('/admin/login');
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/admin(?!\/login)/, { timeout: 15000 });
}
